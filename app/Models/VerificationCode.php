<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * VerificationCode model for public document verification.
 *
 * @property int $id
 * @property string $uuid
 * @property int $document_id
 * @property string $verification_code
 * @property string $short_code
 * @property string|null $qr_code_path
 * @property \Carbon\Carbon|null $expires_at
 * @property int $access_count
 * @property \Carbon\Carbon|null $last_accessed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Document $document
 * @property-read \Illuminate\Database\Eloquent\Collection<VerificationLog> $verificationLogs
 */
class VerificationCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'document_id',
        'verification_code',
        'short_code',
        'qr_code_path',
        'expires_at',
        'access_count',
        'last_accessed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    /**
     * Get the document that owns this verification code.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the verification logs for this code.
     */
    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class);
    }

    /**
     * Check if the verification code has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the verification code is still active (not expired).
     */
    public function isActive(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Increment the access count and update last accessed timestamp.
     */
    public function incrementAccessCount(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Scope a query to only include active (non-expired) verification codes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope a query to only include expired verification codes.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to filter by document.
     */
    public function scopeForDocument(Builder $query, int $documentId): Builder
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope a query to filter by verification code.
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        // Normalize the code (remove dashes, uppercase)
        $normalizedCode = strtoupper(str_replace('-', '', $code));

        return $query->where(function (Builder $q) use ($code, $normalizedCode) {
            $q->where('verification_code', $code)
                ->orWhere('verification_code', $normalizedCode)
                ->orWhere('short_code', $code)
                ->orWhere('short_code', $normalizedCode);
        });
    }

    /**
     * Scope a query to filter by short code.
     */
    public function scopeByShortCode(Builder $query, string $shortCode): Builder
    {
        return $query->where('short_code', strtoupper($shortCode));
    }

    /**
     * Get the full verification URL for this code.
     */
    public function getVerificationUrl(): string
    {
        return route('verify.show', ['code' => $this->verification_code]);
    }

    /**
     * Get the formatted verification code with dashes (XXXX-XXXX-XXXX).
     */
    public function getFormattedCode(): string
    {
        $code = str_replace('-', '', $this->verification_code);

        if (strlen($code) === 12) {
            return implode('-', str_split($code, 4));
        }

        return $this->verification_code;
    }

    /**
     * Get route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'verification_code';
    }

    /**
     * Generate a new unique verification code.
     *
     * Uses charset without confusing characters (0/O, 1/I/L).
     */
    public static function generateCode(): string
    {
        $charset = config('verification.code.charset', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $length = config('verification.code.length', 12);

        $code = '';
        $charsetLength = strlen($charset);

        for ($i = 0; $i < $length; $i++) {
            $code .= $charset[random_int(0, $charsetLength - 1)];
        }

        return $code;
    }

    /**
     * Generate a new unique short code for QR.
     */
    public static function generateShortCode(): string
    {
        $charset = config('verification.code.charset', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $length = config('verification.code.short_length', 6);

        $code = '';
        $charsetLength = strlen($charset);

        for ($i = 0; $i < $length; $i++) {
            $code .= $charset[random_int(0, $charsetLength - 1)];
        }

        return $code;
    }

    /**
     * Format a verification code with dashes.
     */
    public static function formatCode(string $code): string
    {
        $code = strtoupper(str_replace('-', '', $code));

        if (strlen($code) === 12) {
            return implode('-', str_split($code, 4));
        }

        return $code;
    }

    /**
     * Normalize a verification code (remove dashes, uppercase).
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', $code));
    }
}
