<?php

namespace App\Services\Evidence;

use App\Models\ConsentRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConsentCaptureService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService,
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Record explicit consent from signer.
     */
    public function recordConsent(
        Model $signable,
        string $signerEmail,
        string $consentType,
        string $action,
        ?string $screenshotBase64 = null,
        ?array $uiContext = null,
        ?int $signerId = null,
        string $language = 'es'
    ): ConsentRecord {
        $tenant = app('tenant');

        // Get consent text and version
        $legalText = $this->getLegalText($consentType, $language);
        $consentVersion = $this->getConsentVersion($consentType);

        // Hash the legal text for verification
        $legalTextHash = $this->hashingService->hashContent($legalText);

        // Save screenshot if provided
        $screenshotPath = null;
        $screenshotHash = null;
        $screenshotCapturedAt = null;

        if ($screenshotBase64 && config('evidence.consent.capture_screenshot')) {
            $screenshotData = $this->saveScreenshot($screenshotBase64, $tenant?->id, $signerEmail);
            $screenshotPath = $screenshotData['path'];
            $screenshotHash = $screenshotData['hash'];
            $screenshotCapturedAt = now();
        }

        // Build verification data
        $actionTimestamp = now();
        $verificationData = [
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'signer_email' => $signerEmail,
            'consent_type' => $consentType,
            'consent_version' => $consentVersion,
            'legal_text_hash' => $legalTextHash,
            'action' => $action,
            'action_timestamp' => $actionTimestamp->toIso8601String(),
            'screenshot_hash' => $screenshotHash,
            'ui_context' => $uiContext,
        ];

        $verificationHash = $this->hashingService->hashAuditData($verificationData);

        // Create consent record
        $consent = ConsentRecord::create([
            'uuid' => Str::uuid(),
            'tenant_id' => $tenant?->id,
            'signable_type' => get_class($signable),
            'signable_id' => $signable->id,
            'signer_id' => $signerId,
            'signer_email' => $signerEmail,
            'consent_type' => $consentType,
            'consent_version' => $consentVersion,
            'legal_text_hash' => $legalTextHash,
            'legal_text_content' => $legalText,
            'legal_text_language' => $language,
            'action' => $action,
            'action_timestamp' => $actionTimestamp,
            'screenshot_path' => $screenshotPath,
            'screenshot_hash' => $screenshotHash,
            'screenshot_captured_at' => $screenshotCapturedAt,
            'ui_element_id' => $uiContext['element_id'] ?? null,
            'ui_visible_duration_ms' => $uiContext['visible_duration_ms'] ?? null,
            'scroll_to_bottom' => $uiContext['scroll_to_bottom'] ?? false,
            'verification_hash' => $verificationHash,
            'created_at' => now(),
        ]);

        // Get TSA timestamp if required
        if (config('evidence.consent.tsa_required') && $action === 'accepted') {
            $tsaToken = $this->tsaService->getTimestamp($verificationHash, $consent);
            $consent->update(['tsa_token_id' => $tsaToken->id]);
        }

        // Log to audit trail
        $this->auditTrailService->logEvent(
            'evidence.consent_captured',
            $signable,
            [
                'consent_id' => $consent->id,
                'consent_type' => $consentType,
                'consent_version' => $consentVersion,
                'action' => $action,
                'signer_email' => $signerEmail,
                'legal_text_hash' => $legalTextHash,
                'verification_hash' => $verificationHash,
                'has_screenshot' => $screenshotPath !== null,
                'has_tsa' => $consent->tsa_token_id !== null,
            ]
        );

        return $consent;
    }

    /**
     * Record signature consent specifically.
     */
    public function recordSignatureConsent(
        Model $signable,
        string $signerEmail,
        ?string $screenshotBase64 = null,
        ?array $uiContext = null,
        ?int $signerId = null,
        string $language = 'es'
    ): ConsentRecord {
        return $this->recordConsent(
            $signable,
            $signerEmail,
            'signature',
            'accepted',
            $screenshotBase64,
            $uiContext,
            $signerId,
            $language
        );
    }

    /**
     * Revoke a previous consent.
     */
    public function revokeConsent(
        ConsentRecord $originalConsent,
        ?string $reason = null
    ): ConsentRecord {
        return $this->recordConsent(
            $originalConsent->signable,
            $originalConsent->signer_email,
            $originalConsent->consent_type,
            'revoked',
            null,
            ['revocation_reason' => $reason, 'original_consent_id' => $originalConsent->id],
            $originalConsent->signer_id,
            $originalConsent->legal_text_language
        );
    }

    /**
     * Get legal text for consent type.
     */
    public function getLegalText(string $consentType, string $language = 'es'): string
    {
        $texts = config('evidence.consent_texts', []);

        return $texts[$consentType][$language]
            ?? $texts[$consentType]['es']
            ?? $texts[$consentType]['en']
            ?? "Consent text for {$consentType} not configured.";
    }

    /**
     * Get consent version.
     */
    public function getConsentVersion(string $consentType): string
    {
        return config("evidence.consent_versions.{$consentType}", '1.0');
    }

    /**
     * Maximum screenshot size in bytes (5MB).
     */
    private const MAX_SCREENSHOT_SIZE = 5 * 1024 * 1024;

    /**
     * Allowed MIME types for screenshots.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /**
     * Save screenshot to storage.
     *
     * @security Validates MIME type, size, and image integrity before storage.
     */
    private function saveScreenshot(string $base64Data, ?int $tenantId, string $signerEmail): array
    {
        // Remove data URL prefix if present
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);

        // Decode base64
        $imageData = base64_decode($base64Data, true);

        if ($imageData === false) {
            throw new \InvalidArgumentException('Invalid base64 image data');
        }

        // Validate size
        if (strlen($imageData) > self::MAX_SCREENSHOT_SIZE) {
            throw new \InvalidArgumentException('Screenshot exceeds maximum size of 5MB');
        }

        // Validate MIME type using finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid image type. Allowed: PNG, JPEG, WebP');
        }

        // Verify it's actually a valid image by attempting to read its info
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Invalid or corrupted image data');
        }

        // Additional check: ensure image dimensions are reasonable
        [$width, $height] = $imageInfo;
        if ($width > 4096 || $height > 4096 || $width < 10 || $height < 10) {
            throw new \InvalidArgumentException('Image dimensions out of acceptable range');
        }

        // Determine extension from actual MIME type
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        // Generate filename with sanitized email
        $safeEmail = preg_replace('/[^a-z0-9]/i', '_', $signerEmail);
        $filename = sprintf(
            '%s/%s/%s_%s.%s',
            config('evidence.consent.path_prefix', 'consent-screenshots'),
            $tenantId ?? 'global',
            Str::slug(substr($safeEmail, 0, 50)),
            now()->format('Y-m-d_His_u'),
            $extension
        );

        // Save to storage
        $disk = config('evidence.consent.storage_disk', 'local');
        Storage::disk($disk)->put($filename, $imageData);

        // Calculate hash
        $hash = $this->hashingService->hashContent($imageData);

        return [
            'path' => $filename,
            'hash' => $hash,
        ];
    }

    /**
     * Verify consent integrity.
     */
    public function verifyConsent(ConsentRecord $consent): array
    {
        $errors = [];

        // Verify legal text hash
        $currentTextHash = $this->hashingService->hashContent($consent->legal_text_content);
        if ($currentTextHash !== $consent->legal_text_hash) {
            $errors[] = 'Legal text has been modified';
        }

        // Verify screenshot hash if exists
        if ($consent->screenshot_path && $consent->screenshot_hash) {
            $disk = config('evidence.consent.storage_disk', 'local');
            if (Storage::disk($disk)->exists($consent->screenshot_path)) {
                $screenshotContent = Storage::disk($disk)->get($consent->screenshot_path);
                $currentScreenshotHash = $this->hashingService->hashContent($screenshotContent);
                if ($currentScreenshotHash !== $consent->screenshot_hash) {
                    $errors[] = 'Screenshot has been modified';
                }
            } else {
                $errors[] = 'Screenshot file not found';
            }
        }

        // Recalculate verification hash
        // Reconstruct ui_context exactly as it was during creation
        $uiContext = null;
        if ($consent->ui_element_id || $consent->ui_visible_duration_ms || $consent->scroll_to_bottom) {
            $uiContext = [
                'element_id' => $consent->ui_element_id,
                'visible_duration_ms' => $consent->ui_visible_duration_ms,
                'scroll_to_bottom' => $consent->scroll_to_bottom,
            ];
        }

        $verificationData = [
            'signable_type' => $consent->signable_type,
            'signable_id' => $consent->signable_id,
            'signer_email' => $consent->signer_email,
            'consent_type' => $consent->consent_type,
            'consent_version' => $consent->consent_version,
            'legal_text_hash' => $consent->legal_text_hash,
            'action' => $consent->action,
            'action_timestamp' => $consent->action_timestamp->toIso8601String(),
            'screenshot_hash' => $consent->screenshot_hash,
            'ui_context' => $uiContext,
        ];

        $expectedHash = $this->hashingService->hashAuditData($verificationData);
        if ($expectedHash !== $consent->verification_hash) {
            $errors[] = 'Verification hash mismatch - consent data may have been tampered';
        }

        // Verify TSA if exists
        if ($consent->tsa_token_id) {
            $tsaToken = $consent->tsaToken;
            if ($tsaToken && ! $this->tsaService->verifyToken($tsaToken)) {
                $errors[] = 'TSA token verification failed';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get consents for a signable.
     */
    public function getForSignable(Model $signable): \Illuminate\Database\Eloquent\Collection
    {
        return ConsentRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->orderBy('action_timestamp', 'desc')
            ->get();
    }

    /**
     * Get consents for a signer.
     */
    public function getForSigner(string $signerEmail): \Illuminate\Database\Eloquent\Collection
    {
        return ConsentRecord::bySigner($signerEmail)
            ->orderBy('action_timestamp', 'desc')
            ->get();
    }

    /**
     * Check if signer has accepted consent for signable.
     */
    public function hasAcceptedConsent(
        Model $signable,
        string $signerEmail,
        string $consentType
    ): bool {
        return ConsentRecord::where('signable_type', get_class($signable))
            ->where('signable_id', $signable->id)
            ->where('signer_email', $signerEmail)
            ->where('consent_type', $consentType)
            ->where('action', 'accepted')
            ->exists();
    }

    /**
     * Get all available consent types.
     */
    public function getAvailableConsentTypes(): array
    {
        return array_keys(config('evidence.consent_texts', []));
    }
}
