<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Represents an RFC 3161 timestamp token from a TSA.
 *
 * TSA (Time Stamping Authority) tokens provide cryptographic proof
 * of when a hash was created, which is essential for eIDAS compliance.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $hash_algorithm
 * @property string $data_hash
 * @property string $token
 * @property string $provider
 * @property string $status
 * @property \Carbon\Carbon|null $issued_at
 * @property \Carbon\Carbon|null $verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TsaToken extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * Provider enum values.
     */
    public const PROVIDER_FIRMAPROFESIONAL = 'firmaprofesional';

    public const PROVIDER_DIGICERT = 'digicert';

    public const PROVIDER_SECTIGO = 'sectigo';

    public const PROVIDER_MOCK = 'mock';

    /**
     * Status enum values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'hash_algorithm',
        'data_hash',
        'token',
        'provider',
        'status',
        'issued_at',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'issued_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TsaToken $token) {
            if (empty($token->uuid)) {
                $token->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the audit trail entries using this token.
     *
     * @return HasMany<AuditTrailEntry>
     */
    public function auditTrailEntries(): HasMany
    {
        return $this->hasMany(AuditTrailEntry::class);
    }

    /**
     * Check if the token is valid.
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    /**
     * Check if the token is pending verification.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the token has been verified.
     */
    public function hasBeenVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Mark the token as verified.
     *
     * @param  bool  $isValid  Whether the verification passed
     */
    public function markAsVerified(bool $isValid = true): void
    {
        $this->update([
            'status' => $isValid ? self::STATUS_VALID : self::STATUS_INVALID,
            'verified_at' => now(),
        ]);
    }

    /**
     * Get the decoded token data.
     *
     * @return string Binary token data
     */
    public function getDecodedToken(): string
    {
        return base64_decode($this->token);
    }

    /**
     * Scope to filter by provider.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TsaToken>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TsaToken>
     */
    public function scopeFromProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter valid tokens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TsaToken>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TsaToken>
     */
    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_VALID);
    }

    /**
     * Scope to filter pending tokens.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TsaToken>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TsaToken>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter by hash.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TsaToken>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TsaToken>
     */
    public function scopeForHash($query, string $hash)
    {
        return $query->where('data_hash', $hash);
    }

    /**
     * Get all valid providers.
     *
     * @return array<string>
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_FIRMAPROFESIONAL,
            self::PROVIDER_DIGICERT,
            self::PROVIDER_SECTIGO,
            self::PROVIDER_MOCK,
        ];
    }

    /**
     * Get all valid statuses.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_VALID,
            self::STATUS_INVALID,
            self::STATUS_EXPIRED,
        ];
    }
}
