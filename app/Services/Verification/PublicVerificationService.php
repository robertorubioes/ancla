<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Document;
use App\Models\VerificationCode;
use App\Models\VerificationLog;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service for public document verification.
 *
 * Provides verification by code or hash, calculates confidence levels,
 * and logs all verification attempts.
 *
 * @see ADR-007 in docs/architecture/adr-007-sprint3-retention-verification-upload.md
 */
class PublicVerificationService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly AuditTrailService $auditTrailService,
        private readonly TsaService $tsaService,
    ) {}

    /**
     * Verify a document by verification code.
     *
     * @param  string  $code  The verification code (with or without dashes)
     */
    public function verifyByCode(string $code): VerificationResult
    {
        // Normalize the code
        $normalizedCode = VerificationCode::normalizeCode($code);

        // Check minimum code length
        if (strlen($normalizedCode) < 6) {
            $this->logVerificationAttempt(null, 'invalid_code', $code);

            return VerificationResult::invalidCode();
        }

        // Try to get from cache if enabled
        $cacheKey = config('verification.cache.prefix')."code:{$normalizedCode}";
        if (config('verification.cache.enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof VerificationResult) {
                return $cached;
            }
        }

        // Find the verification code
        $verificationCode = VerificationCode::byCode($normalizedCode)
            ->with(['document.uploadTsaToken'])
            ->first();

        if (! $verificationCode) {
            $this->logVerificationAttempt(null, 'invalid_code', $normalizedCode);

            return VerificationResult::notFound();
        }

        // Check if code has expired
        if ($verificationCode->isExpired()) {
            $this->logVerificationAttempt($verificationCode, 'expired');

            return VerificationResult::expired();
        }

        // Get the document
        $document = $verificationCode->document;
        if (! $document) {
            $this->logVerificationAttempt($verificationCode, 'document_not_found');

            return VerificationResult::notFound('Document not found');
        }

        // Perform full integrity verification
        $integrityResult = $this->verifyFullIntegrity($document);

        // Build the verification result
        $result = $integrityResult->isValid
            ? VerificationResult::success(
                confidenceScore: $integrityResult->totalScore,
                checks: $integrityResult->getChecksArray(),
                document: $document,
                metadata: [
                    'verification_code' => $verificationCode->verification_code,
                    'integrity_details' => $integrityResult->details,
                ]
            )
            : VerificationResult::failure(
                errorMessage: implode('; ', $integrityResult->errors),
                confidenceScore: $integrityResult->totalScore,
                checks: $integrityResult->getChecksArray(),
                document: $document,
                metadata: [
                    'verification_code' => $verificationCode->verification_code,
                    'integrity_details' => $integrityResult->details,
                ]
            );

        // Cache the result
        if (config('verification.cache.enabled', true)) {
            Cache::put(
                $cacheKey,
                $result,
                now()->addMinutes(config('verification.cache.ttl_minutes', 5))
            );
        }

        // Increment access count
        $verificationCode->incrementAccessCount();

        // Log the verification
        $this->logVerificationAttempt(
            $verificationCode,
            $result->isValid ? 'success' : 'invalid_code',
            $normalizedCode,
            $result
        );

        return $result;
    }

    /**
     * Verify a document by its hash.
     *
     * @param  string  $hash  The SHA-256 hash of the document
     */
    public function verifyByHash(string $hash): VerificationResult
    {
        // Validate hash format
        if (! $this->hashingService->isValidHash($hash)) {
            return VerificationResult::failure('Invalid hash format');
        }

        // Normalize hash to lowercase
        $hash = strtolower($hash);

        // Try to get from cache
        $cacheKey = config('verification.cache.prefix')."hash:{$hash}";
        if (config('verification.cache.enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof VerificationResult) {
                return $cached;
            }
        }

        // Find document by hash
        $document = Document::where('sha256_hash', $hash)
            ->with(['uploadTsaToken'])
            ->first();

        if (! $document) {
            return VerificationResult::notFound('No document found with provided hash');
        }

        // Find verification code for this document
        $verificationCode = VerificationCode::forDocument($document->id)->first();

        // Perform integrity verification
        $integrityResult = $this->verifyFullIntegrity($document);

        $result = $integrityResult->isValid
            ? VerificationResult::success(
                confidenceScore: $integrityResult->totalScore,
                checks: $integrityResult->getChecksArray(),
                document: $document,
                metadata: [
                    'verified_by' => 'hash',
                    'verification_code' => $verificationCode?->verification_code,
                ]
            )
            : VerificationResult::failure(
                errorMessage: implode('; ', $integrityResult->errors),
                confidenceScore: $integrityResult->totalScore,
                checks: $integrityResult->getChecksArray(),
                document: $document,
                metadata: ['verified_by' => 'hash']
            );

        // Cache the result
        if (config('verification.cache.enabled', true)) {
            Cache::put(
                $cacheKey,
                $result,
                now()->addMinutes(config('verification.cache.ttl_minutes', 5))
            );
        }

        // Log verification if we have a verification code
        if ($verificationCode) {
            $this->logVerificationAttempt(
                $verificationCode,
                $result->isValid ? 'success' : 'invalid_code',
                $hash,
                $result
            );
        }

        return $result;
    }

    /**
     * Get verification details for a code.
     *
     * @param  string  $code  The verification code
     * @return array<string, mixed>|null Details or null if not found
     */
    public function getVerificationDetails(string $code): ?array
    {
        $normalizedCode = VerificationCode::normalizeCode($code);

        $verificationCode = VerificationCode::byCode($normalizedCode)
            ->with([
                'document.uploadTsaToken',
                'document.user',
                'verificationLogs' => fn ($q) => $q->latest()->limit(10),
            ])
            ->first();

        if (! $verificationCode || ! $verificationCode->document) {
            return null;
        }

        $document = $verificationCode->document;
        $integrityResult = $this->verifyFullIntegrity($document);

        return [
            'document' => [
                'filename' => $document->original_filename,
                'hash' => $document->sha256_hash,
                'algorithm' => $document->hash_algorithm,
                'pages' => $document->page_count,
                'size' => $document->file_size,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'uploaded_by' => $document->user?->name ?? 'Unknown',
            ],
            'verification' => [
                'code' => $verificationCode->getFormattedCode(),
                'short_code' => $verificationCode->short_code,
                'created_at' => $verificationCode->created_at?->toIso8601String(),
                'expires_at' => $verificationCode->expires_at?->toIso8601String(),
                'access_count' => $verificationCode->access_count,
                'last_accessed_at' => $verificationCode->last_accessed_at?->toIso8601String(),
            ],
            'integrity' => [
                'is_valid' => $integrityResult->isValid,
                'confidence_score' => $integrityResult->totalScore,
                'confidence_level' => $integrityResult->getConfidenceLevel(),
                'checks' => $integrityResult->getChecksArray(),
            ],
            'tsa' => $document->uploadTsaToken ? [
                'provider' => $document->uploadTsaToken->provider,
                'timestamp' => $document->uploadTsaToken->issued_at?->toIso8601String(),
                'valid_until' => $document->uploadTsaToken->valid_until?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Calculate confidence level based on available evidence.
     *
     * @return int Confidence score (0-100)
     */
    public function calculateConfidenceLevel(Document $document): int
    {
        $integrityResult = $this->verifyFullIntegrity($document);

        return $integrityResult->totalScore;
    }

    /**
     * Verify full document integrity.
     *
     * Performs all verification checks and calculates confidence score.
     */
    public function verifyFullIntegrity(Document $document): IntegrityCheckResult
    {
        $checks = [];
        $details = [];
        $errors = [];

        // 1. Verify document hash
        $documentHashValid = false;
        try {
            $documentHashValid = $this->hashingService->verifyDocumentHash(
                $document->storage_path,
                $document->sha256_hash,
                $document->storage_disk
            );
            if (! $documentHashValid) {
                $errors[] = 'Document hash mismatch - file may have been altered';
            }
            $details['document_hash'] = [
                'expected' => $document->sha256_hash,
                'verified' => $documentHashValid,
            ];
        } catch (\Exception $e) {
            $errors[] = 'Failed to verify document hash: '.$e->getMessage();
            $details['document_hash'] = ['error' => $e->getMessage()];
        }
        $checks['document_hash_valid'] = $documentHashValid;

        // 2. Verify audit trail chain
        $chainHashValid = false;
        try {
            $chainResult = $this->auditTrailService->verifyChain(
                Document::class,
                $document->id
            );
            $chainHashValid = $chainResult->valid;
            if (! $chainHashValid) {
                $errors = array_merge($errors, $chainResult->errors);
            }
            $details['chain_verification'] = [
                'entries_verified' => $chainResult->entriesVerified,
                'valid' => $chainResult->valid,
                'first_sequence' => $chainResult->firstSequence,
                'last_sequence' => $chainResult->lastSequence,
            ];
        } catch (\Exception $e) {
            $errors[] = 'Failed to verify audit trail: '.$e->getMessage();
            $details['chain_verification'] = ['error' => $e->getMessage()];
        }
        $checks['chain_hash_valid'] = $chainHashValid;

        // 3. Verify TSA timestamp
        $tsaValid = false;
        if ($document->uploadTsaToken) {
            try {
                $tsaValid = $this->tsaService->verifyTimestamp($document->uploadTsaToken);
                $details['tsa_verification'] = [
                    'provider' => $document->uploadTsaToken->provider,
                    'timestamp' => $document->uploadTsaToken->issued_at?->toIso8601String(),
                    'valid' => $tsaValid,
                ];
            } catch (\Exception $e) {
                $details['tsa_verification'] = ['error' => $e->getMessage()];
            }
        } else {
            $details['tsa_verification'] = ['present' => false];
        }
        $checks['tsa_timestamp_valid'] = $tsaValid;

        // 4. Check for device fingerprint
        $hasDeviceFingerprint = $this->checkEvidencePresence($document, 'device_fingerprint');
        $checks['device_fingerprint_present'] = $hasDeviceFingerprint;
        $details['device_fingerprint'] = ['present' => $hasDeviceFingerprint];

        // 5. Check for geolocation
        $hasGeolocation = $this->checkEvidencePresence($document, 'geolocation');
        $checks['geolocation_present'] = $hasGeolocation;
        $details['geolocation'] = ['present' => $hasGeolocation];

        // 6. Check for IP resolution
        $hasIpResolution = $this->checkEvidencePresence($document, 'ip_resolution');
        $checks['ip_resolution_present'] = $hasIpResolution;
        $details['ip_resolution'] = ['present' => $hasIpResolution];

        // 7. Check for consent records
        $hasConsentRecords = $this->checkEvidencePresence($document, 'consent');
        $checks['consent_records_present'] = $hasConsentRecords;
        $details['consent_records'] = ['present' => $hasConsentRecords];

        return IntegrityCheckResult::fromChecks($checks, $details, $errors);
    }

    /**
     * Check if a specific type of evidence is present for the document.
     */
    private function checkEvidencePresence(Document $document, string $evidenceType): bool
    {
        // Check through evidence packages or related models
        // For now, check if there are audit trail entries indicating the evidence was captured
        $eventTypes = match ($evidenceType) {
            'device_fingerprint' => ['evidence.device_captured', 'device.fingerprint.captured'],
            'geolocation' => ['evidence.geolocation_captured', 'geolocation.captured'],
            'ip_resolution' => ['evidence.ip_resolved', 'ip.resolved'],
            'consent' => ['consent.captured', 'evidence.consent_captured'],
            default => [],
        };

        if (empty($eventTypes)) {
            return false;
        }

        return DB::table('audit_trail_entries')
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->whereIn('event_type', $eventTypes)
            ->exists();
    }

    /**
     * Log a verification attempt.
     */
    private function logVerificationAttempt(
        ?VerificationCode $verificationCode,
        string $result,
        ?string $identifier = null,
        ?VerificationResult $verificationResult = null
    ): void {
        if (! config('verification.logging.enabled', true)) {
            return;
        }

        if (! $verificationCode) {
            // Log failed attempts without verification code to a separate mechanism
            // For now, we'll just return as we can't log without a verification_code_id
            return;
        }

        $details = [
            'identifier_used' => $identifier,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($verificationResult) {
            $details['confidence_score'] = $verificationResult->confidenceScore;
            $details['checks'] = $verificationResult->checks;
        }

        VerificationLog::create([
            'uuid' => Str::uuid(),
            'verification_code_id' => $verificationCode->id,
            'ip_address' => config('verification.logging.log_ip', true)
                ? (request()->ip() ?? '0.0.0.0')
                : '0.0.0.0',
            'user_agent' => config('verification.logging.log_user_agent', true)
                ? request()->userAgent()
                : null,
            'result' => $result,
            'confidence_level' => $verificationResult?->confidenceLevel,
            'details' => $details,
        ]);
    }

    /**
     * Create a verification code for a document.
     */
    public function createVerificationCode(Document $document, ?int $expirationDays = null): VerificationCode
    {
        $code = VerificationCode::generateCode();
        $shortCode = VerificationCode::generateShortCode();

        // Ensure uniqueness
        while (VerificationCode::where('verification_code', $code)->exists()) {
            $code = VerificationCode::generateCode();
        }

        while (VerificationCode::where('short_code', $shortCode)->exists()) {
            $shortCode = VerificationCode::generateShortCode();
        }

        $expirationDays = $expirationDays ?? config('verification.code.default_expiration_days');

        return VerificationCode::create([
            'uuid' => Str::uuid(),
            'document_id' => $document->id,
            'verification_code' => $code,
            'short_code' => $shortCode,
            'expires_at' => $expirationDays ? now()->addDays($expirationDays) : null,
            'access_count' => 0,
        ]);
    }
}
