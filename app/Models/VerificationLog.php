<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VerificationLog model for tracking verification attempts.
 *
 * @property int $id
 * @property string $uuid
 * @property int $verification_code_id
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string $result
 * @property string|null $confidence_level
 * @property array|null $details
 * @property \Carbon\Carbon $created_at
 * @property-read VerificationCode $verificationCode
 */
class VerificationLog extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     * We only use created_at for logs.
     */
    public const UPDATED_AT = null;

    /**
     * Result constants.
     */
    public const RESULT_SUCCESS = 'success';

    public const RESULT_INVALID_CODE = 'invalid_code';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_DOCUMENT_NOT_FOUND = 'document_not_found';

    /**
     * Confidence level constants.
     */
    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'verification_code_id',
        'ip_address',
        'user_agent',
        'result',
        'confidence_level',
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the verification code that owns this log entry.
     */
    public function verificationCode(): BelongsTo
    {
        return $this->belongsTo(VerificationCode::class);
    }

    /**
     * Check if the verification was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->result === self::RESULT_SUCCESS;
    }

    /**
     * Check if the verification failed due to invalid code.
     */
    public function wasInvalidCode(): bool
    {
        return $this->result === self::RESULT_INVALID_CODE;
    }

    /**
     * Check if the verification failed due to expired code.
     */
    public function wasExpired(): bool
    {
        return $this->result === self::RESULT_EXPIRED;
    }

    /**
     * Check if the verification failed because the document was not found.
     */
    public function wasDocumentNotFound(): bool
    {
        return $this->result === self::RESULT_DOCUMENT_NOT_FOUND;
    }

    /**
     * Check if the confidence level is high.
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence_level === self::CONFIDENCE_HIGH;
    }

    /**
     * Check if the confidence level is medium.
     */
    public function isMediumConfidence(): bool
    {
        return $this->confidence_level === self::CONFIDENCE_MEDIUM;
    }

    /**
     * Check if the confidence level is low.
     */
    public function isLowConfidence(): bool
    {
        return $this->confidence_level === self::CONFIDENCE_LOW;
    }

    /**
     * Scope a query to only include successful verifications.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('result', self::RESULT_SUCCESS);
    }

    /**
     * Scope a query to only include failed verifications.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('result', '!=', self::RESULT_SUCCESS);
    }

    /**
     * Scope a query to filter by result.
     */
    public function scopeWithResult(Builder $query, string $result): Builder
    {
        return $query->where('result', $result);
    }

    /**
     * Scope a query to filter by confidence level.
     */
    public function scopeWithConfidence(Builder $query, string $confidenceLevel): Builder
    {
        return $query->where('confidence_level', $confidenceLevel);
    }

    /**
     * Scope a query to filter by IP address.
     */
    public function scopeFromIp(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope a query to filter by verification code.
     */
    public function scopeForVerificationCode(Builder $query, int $verificationCodeId): Builder
    {
        return $query->where('verification_code_id', $verificationCodeId);
    }

    /**
     * Scope a query to filter logs from a specific time range.
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get recent logs.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get a specific detail from the details array.
     */
    public function getDetail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }

    /**
     * Check if a specific check passed in the verification.
     */
    public function checkPassed(string $checkName): bool
    {
        $checks = $this->details['checks'] ?? [];

        foreach ($checks as $check) {
            if (($check['name'] ?? '') === $checkName) {
                return $check['passed'] ?? false;
            }
        }

        return false;
    }

    /**
     * Get all checks from the verification.
     */
    public function getChecks(): array
    {
        return $this->details['checks'] ?? [];
    }

    /**
     * Get the confidence score from the details.
     */
    public function getConfidenceScore(): int
    {
        return (int) ($this->details['confidence_score'] ?? 0);
    }
}
