<?php

declare(strict_types=1);

namespace App\Services\Verification;

/**
 * IntegrityCheckResult DTO for full document integrity verification.
 *
 * Contains detailed results of each integrity check performed
 * on a document for verification purposes.
 */
final readonly class IntegrityCheckResult
{
    /**
     * @param  bool  $isValid  Overall integrity check result
     * @param  bool  $documentHashValid  Document hash verification result
     * @param  bool  $chainHashValid  Audit trail chain hash verification result
     * @param  bool  $tsaTimestampValid  TSA timestamp verification result
     * @param  bool  $deviceFingerprintPresent  Device fingerprint data present
     * @param  bool  $geolocationPresent  Geolocation data present
     * @param  bool  $ipResolutionPresent  IP resolution data present
     * @param  bool  $consentRecordsPresent  Consent records present
     * @param  int  $totalScore  Total confidence score (0-100)
     * @param  array<string, mixed>  $details  Detailed check results
     * @param  array<int, string>  $errors  List of error messages
     */
    public function __construct(
        public bool $isValid,
        public bool $documentHashValid,
        public bool $chainHashValid,
        public bool $tsaTimestampValid,
        public bool $deviceFingerprintPresent,
        public bool $geolocationPresent,
        public bool $ipResolutionPresent,
        public bool $consentRecordsPresent,
        public int $totalScore,
        public array $details = [],
        public array $errors = [],
    ) {}

    /**
     * Create a result for a fully valid document.
     *
     * @param  array<string, mixed>  $details  Additional details
     */
    public static function valid(array $details = []): self
    {
        return new self(
            isValid: true,
            documentHashValid: true,
            chainHashValid: true,
            tsaTimestampValid: true,
            deviceFingerprintPresent: true,
            geolocationPresent: true,
            ipResolutionPresent: true,
            consentRecordsPresent: true,
            totalScore: 100,
            details: $details,
            errors: [],
        );
    }

    /**
     * Create a result from individual check results.
     *
     * @param  array<string, bool>  $checks  Individual check results
     * @param  array<string, mixed>  $details  Additional details
     * @param  array<int, string>  $errors  Error messages
     */
    public static function fromChecks(array $checks, array $details = [], array $errors = []): self
    {
        $scorePoints = config('verification.score_points', [
            'document_hash_valid' => 20,
            'chain_hash_valid' => 20,
            'tsa_timestamp_valid' => 20,
            'device_fingerprint' => 15,
            'geolocation' => 10,
            'ip_resolution' => 10,
            'consent_records' => 5,
        ]);

        $totalScore = 0;

        if ($checks['document_hash_valid'] ?? false) {
            $totalScore += $scorePoints['document_hash_valid'];
        }
        if ($checks['chain_hash_valid'] ?? false) {
            $totalScore += $scorePoints['chain_hash_valid'];
        }
        if ($checks['tsa_timestamp_valid'] ?? false) {
            $totalScore += $scorePoints['tsa_timestamp_valid'];
        }
        if ($checks['device_fingerprint_present'] ?? false) {
            $totalScore += $scorePoints['device_fingerprint'];
        }
        if ($checks['geolocation_present'] ?? false) {
            $totalScore += $scorePoints['geolocation'];
        }
        if ($checks['ip_resolution_present'] ?? false) {
            $totalScore += $scorePoints['ip_resolution'];
        }
        if ($checks['consent_records_present'] ?? false) {
            $totalScore += $scorePoints['consent_records'];
        }

        // Minimum requirements for validity: document hash and chain must be valid
        $isValid = ($checks['document_hash_valid'] ?? false) && ($checks['chain_hash_valid'] ?? false);

        return new self(
            isValid: $isValid,
            documentHashValid: $checks['document_hash_valid'] ?? false,
            chainHashValid: $checks['chain_hash_valid'] ?? false,
            tsaTimestampValid: $checks['tsa_timestamp_valid'] ?? false,
            deviceFingerprintPresent: $checks['device_fingerprint_present'] ?? false,
            geolocationPresent: $checks['geolocation_present'] ?? false,
            ipResolutionPresent: $checks['ip_resolution_present'] ?? false,
            consentRecordsPresent: $checks['consent_records_present'] ?? false,
            totalScore: $totalScore,
            details: $details,
            errors: $errors,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<int, string>  $errors  Error messages
     * @param  array<string, mixed>  $details  Additional details
     */
    public static function failed(array $errors, array $details = []): self
    {
        return new self(
            isValid: false,
            documentHashValid: false,
            chainHashValid: false,
            tsaTimestampValid: false,
            deviceFingerprintPresent: false,
            geolocationPresent: false,
            ipResolutionPresent: false,
            consentRecordsPresent: false,
            totalScore: 0,
            details: $details,
            errors: $errors,
        );
    }

    /**
     * Get the confidence level based on total score.
     */
    public function getConfidenceLevel(): string
    {
        return VerificationResult::calculateConfidenceLevel($this->totalScore);
    }

    /**
     * Check if the confidence level is high.
     */
    public function isHighConfidence(): bool
    {
        return $this->getConfidenceLevel() === 'high';
    }

    /**
     * Check if the confidence level is medium.
     */
    public function isMediumConfidence(): bool
    {
        return $this->getConfidenceLevel() === 'medium';
    }

    /**
     * Check if the confidence level is low.
     */
    public function isLowConfidence(): bool
    {
        return $this->getConfidenceLevel() === 'low';
    }

    /**
     * Get all checks as an array.
     *
     * @return array<int, array{name: string, passed: bool, points: int}>
     */
    public function getChecksArray(): array
    {
        $scorePoints = config('verification.score_points', [
            'document_hash_valid' => 20,
            'chain_hash_valid' => 20,
            'tsa_timestamp_valid' => 20,
            'device_fingerprint' => 15,
            'geolocation' => 10,
            'ip_resolution' => 10,
            'consent_records' => 5,
        ]);

        return [
            [
                'name' => 'document_hash',
                'passed' => $this->documentHashValid,
                'points' => $this->documentHashValid ? $scorePoints['document_hash_valid'] : 0,
                'message' => $this->documentHashValid ? 'Document hash is valid' : 'Document hash verification failed',
            ],
            [
                'name' => 'chain_hash',
                'passed' => $this->chainHashValid,
                'points' => $this->chainHashValid ? $scorePoints['chain_hash_valid'] : 0,
                'message' => $this->chainHashValid ? 'Audit trail chain is valid' : 'Audit trail chain verification failed',
            ],
            [
                'name' => 'tsa_timestamp',
                'passed' => $this->tsaTimestampValid,
                'points' => $this->tsaTimestampValid ? $scorePoints['tsa_timestamp_valid'] : 0,
                'message' => $this->tsaTimestampValid ? 'TSA timestamp is valid' : 'TSA timestamp verification failed or missing',
            ],
            [
                'name' => 'device_fingerprint',
                'passed' => $this->deviceFingerprintPresent,
                'points' => $this->deviceFingerprintPresent ? $scorePoints['device_fingerprint'] : 0,
                'message' => $this->deviceFingerprintPresent ? 'Device fingerprint present' : 'No device fingerprint data',
            ],
            [
                'name' => 'geolocation',
                'passed' => $this->geolocationPresent,
                'points' => $this->geolocationPresent ? $scorePoints['geolocation'] : 0,
                'message' => $this->geolocationPresent ? 'Geolocation data present' : 'No geolocation data',
            ],
            [
                'name' => 'ip_resolution',
                'passed' => $this->ipResolutionPresent,
                'points' => $this->ipResolutionPresent ? $scorePoints['ip_resolution'] : 0,
                'message' => $this->ipResolutionPresent ? 'IP resolution data present' : 'No IP resolution data',
            ],
            [
                'name' => 'consent_records',
                'passed' => $this->consentRecordsPresent,
                'points' => $this->consentRecordsPresent ? $scorePoints['consent_records'] : 0,
                'message' => $this->consentRecordsPresent ? 'Consent records present' : 'No consent records',
            ],
        ];
    }

    /**
     * Get a summary of passed checks.
     *
     * @return array<int, string>
     */
    public function getPassedCheckNames(): array
    {
        return array_map(
            fn (array $check) => $check['name'],
            array_filter($this->getChecksArray(), fn (array $check) => $check['passed'])
        );
    }

    /**
     * Get a summary of failed checks.
     *
     * @return array<int, string>
     */
    public function getFailedCheckNames(): array
    {
        return array_values(array_map(
            fn (array $check) => $check['name'],
            array_filter($this->getChecksArray(), fn (array $check) => ! $check['passed'])
        ));
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'confidence' => [
                'score' => $this->totalScore,
                'level' => $this->getConfidenceLevel(),
            ],
            'checks' => [
                'document_hash_valid' => $this->documentHashValid,
                'chain_hash_valid' => $this->chainHashValid,
                'tsa_timestamp_valid' => $this->tsaTimestampValid,
                'device_fingerprint_present' => $this->deviceFingerprintPresent,
                'geolocation_present' => $this->geolocationPresent,
                'ip_resolution_present' => $this->ipResolutionPresent,
                'consent_records_present' => $this->consentRecordsPresent,
            ],
            'details' => $this->details,
            'errors' => $this->errors,
        ];
    }
}
