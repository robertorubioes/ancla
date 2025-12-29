<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Document;

/**
 * VerificationResult DTO for public document verification.
 *
 * Encapsulates the result of a document verification process including
 * validity status, confidence score, and detailed check results.
 */
final readonly class VerificationResult
{
    /**
     * @param  bool  $isValid  Whether the document verification passed
     * @param  int  $confidenceScore  Confidence score from 0-100
     * @param  string  $confidenceLevel  Confidence level: 'high', 'medium', 'low'
     * @param  array<int, array{name: string, passed: bool, points: int, message?: string}>  $checks  List of verification checks performed
     * @param  string|null  $errorMessage  Error message if verification failed
     * @param  Document|null  $document  The verified document (if found)
     * @param  array<string, mixed>  $metadata  Additional metadata about the verification
     */
    public function __construct(
        public bool $isValid,
        public int $confidenceScore,
        public string $confidenceLevel,
        public array $checks = [],
        public ?string $errorMessage = null,
        public ?Document $document = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful verification result.
     *
     * @param  int  $confidenceScore  The confidence score (0-100)
     * @param  array<int, array{name: string, passed: bool, points: int, message?: string}>  $checks  The checks performed
     * @param  Document  $document  The verified document
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function success(
        int $confidenceScore,
        array $checks,
        Document $document,
        array $metadata = [],
    ): self {
        return new self(
            isValid: true,
            confidenceScore: $confidenceScore,
            confidenceLevel: self::calculateConfidenceLevel($confidenceScore),
            checks: $checks,
            errorMessage: null,
            document: $document,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed verification result.
     *
     * @param  string  $errorMessage  The error message
     * @param  int  $confidenceScore  The confidence score (0-100)
     * @param  array<int, array{name: string, passed: bool, points: int, message?: string}>  $checks  The checks performed
     * @param  Document|null  $document  The document (if found)
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function failure(
        string $errorMessage,
        int $confidenceScore = 0,
        array $checks = [],
        ?Document $document = null,
        array $metadata = [],
    ): self {
        return new self(
            isValid: false,
            confidenceScore: $confidenceScore,
            confidenceLevel: self::calculateConfidenceLevel($confidenceScore),
            checks: $checks,
            errorMessage: $errorMessage,
            document: $document,
            metadata: $metadata,
        );
    }

    /**
     * Create a "not found" verification result.
     */
    public static function notFound(string $message = 'Verification code not found'): self
    {
        return new self(
            isValid: false,
            confidenceScore: 0,
            confidenceLevel: 'low',
            checks: [],
            errorMessage: $message,
            document: null,
            metadata: ['result' => 'not_found'],
        );
    }

    /**
     * Create an "expired" verification result.
     */
    public static function expired(): self
    {
        return new self(
            isValid: false,
            confidenceScore: 0,
            confidenceLevel: 'low',
            checks: [],
            errorMessage: 'Verification code has expired',
            document: null,
            metadata: ['result' => 'expired'],
        );
    }

    /**
     * Create an "invalid code" verification result.
     */
    public static function invalidCode(): self
    {
        return new self(
            isValid: false,
            confidenceScore: 0,
            confidenceLevel: 'low',
            checks: [],
            errorMessage: 'Invalid verification code format',
            document: null,
            metadata: ['result' => 'invalid_code'],
        );
    }

    /**
     * Calculate confidence level from score.
     */
    public static function calculateConfidenceLevel(int $score): string
    {
        $highThreshold = config('verification.confidence.high', 90);
        $mediumThreshold = config('verification.confidence.medium', 70);

        return match (true) {
            $score >= $highThreshold => 'high',
            $score >= $mediumThreshold => 'medium',
            default => 'low',
        };
    }

    /**
     * Check if the confidence level is high.
     */
    public function isHighConfidence(): bool
    {
        return $this->confidenceLevel === 'high';
    }

    /**
     * Check if the confidence level is medium.
     */
    public function isMediumConfidence(): bool
    {
        return $this->confidenceLevel === 'medium';
    }

    /**
     * Check if the confidence level is low.
     */
    public function isLowConfidence(): bool
    {
        return $this->confidenceLevel === 'low';
    }

    /**
     * Get all passed checks.
     *
     * @return array<int, array{name: string, passed: bool, points: int, message?: string}>
     */
    public function getPassedChecks(): array
    {
        return array_filter($this->checks, fn (array $check) => $check['passed'] === true);
    }

    /**
     * Get all failed checks.
     *
     * @return array<int, array{name: string, passed: bool, points: int, message?: string}>
     */
    public function getFailedChecks(): array
    {
        return array_filter($this->checks, fn (array $check) => $check['passed'] === false);
    }

    /**
     * Get the count of passed checks.
     */
    public function getPassedCheckCount(): int
    {
        return count($this->getPassedChecks());
    }

    /**
     * Get the count of failed checks.
     */
    public function getFailedCheckCount(): int
    {
        return count($this->getFailedChecks());
    }

    /**
     * Get a specific metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid,
            'confidence' => [
                'score' => $this->confidenceScore,
                'level' => $this->confidenceLevel,
            ],
            'document' => $this->document ? [
                'filename' => $this->document->original_filename,
                'hash' => 'sha256:'.$this->document->sha256_hash,
                'uploaded_at' => $this->document->created_at?->toIso8601String(),
                'pages' => $this->document->page_count,
            ] : null,
            'verification' => [
                'checks' => array_map(fn (array $check) => [
                    'name' => $check['name'],
                    'passed' => $check['passed'],
                    'message' => $check['message'] ?? null,
                ], $this->checks),
                'passed_count' => $this->getPassedCheckCount(),
                'failed_count' => $this->getFailedCheckCount(),
            ],
            'error' => $this->errorMessage,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Convert to JSON response format.
     *
     * @return array<string, mixed>
     */
    public function toResponse(): array
    {
        $response = [
            'valid' => $this->isValid,
            'confidence' => [
                'score' => $this->confidenceScore,
                'level' => $this->confidenceLevel,
            ],
        ];

        if ($this->document) {
            $response['document'] = [
                'filename' => $this->document->original_filename,
                'hash' => 'sha256:'.$this->document->sha256_hash,
                'uploaded_at' => $this->document->created_at?->toIso8601String(),
                'pages' => $this->document->page_count,
            ];

            $response['verification'] = [
                'document_integrity' => $this->checkPassed('document_hash'),
                'chain_integrity' => $this->checkPassed('chain_hash'),
                'tsa_valid' => $this->checkPassed('tsa_timestamp'),
                'timestamp' => $this->document->created_at?->toIso8601String(),
            ];
        }

        if ($this->errorMessage) {
            $response['error'] = $this->errorMessage;
        }

        $response['verified_at'] = now()->toIso8601String();

        return $response;
    }

    /**
     * Check if a specific check passed.
     */
    private function checkPassed(string $checkName): bool
    {
        foreach ($this->checks as $check) {
            if ($check['name'] === $checkName) {
                return $check['passed'];
            }
        }

        return false;
    }
}
