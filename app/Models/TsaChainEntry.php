<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * TsaChainEntry model for individual entries in a TSA re-sealing chain.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tsa_chain_id
 * @property int $sequence_number
 * @property int $tsa_token_id
 * @property string|null $previous_entry_hash
 * @property string $cumulative_hash
 * @property string $sealed_hash
 * @property string $reseal_reason
 * @property string $tsa_provider
 * @property string $algorithm_used
 * @property Carbon $timestamp_value
 * @property Carbon $sealed_at
 * @property Carbon|null $expires_at
 * @property int|null $previous_entry_id
 * @property array|null $metadata
 * @property Carbon $created_at
 */
class TsaChainEntry extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Reseal reason constants.
     */
    public const REASON_INITIAL = 'initial';

    public const REASON_SCHEDULED = 'scheduled';

    public const REASON_ALGORITHM_UPGRADE = 'algorithm_upgrade';

    public const REASON_CERTIFICATE_EXPIRY = 'certificate_expiry';

    public const REASON_MANUAL = 'manual';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tsa_chain_id',
        'sequence_number',
        'tsa_token_id',
        'previous_entry_hash',
        'cumulative_hash',
        'sealed_hash',
        'reseal_reason',
        'tsa_provider',
        'algorithm_used',
        'timestamp_value',
        'sealed_at',
        'expires_at',
        'previous_entry_id',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp_value' => 'datetime:Y-m-d H:i:s.u',
        'sealed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'sequence_number' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the TSA chain this entry belongs to.
     */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(TsaChain::class, 'tsa_chain_id');
    }

    /**
     * Get the TSA token for this entry.
     */
    public function tsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class);
    }

    /**
     * Get the previous entry in the chain.
     */
    public function previousEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_entry_id');
    }

    /**
     * Check if this is the initial entry (sequence 0).
     */
    public function isInitialEntry(): bool
    {
        return $this->sequence_number === 0;
    }

    /**
     * Check if this entry is a reseal (sequence > 0).
     */
    public function isReseal(): bool
    {
        return $this->sequence_number > 0;
    }

    /**
     * Check if the TSA certificate is expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the TSA certificate is expiring soon.
     */
    public function isExpiringSoon(int $daysAhead = 90): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->diffInDays(now()) <= $daysAhead;
    }

    /**
     * Get the days until this entry's certificate expires.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    /**
     * Get the age of this entry in days.
     */
    public function getAgeDaysAttribute(): int
    {
        return (int) $this->sealed_at->diffInDays(now());
    }

    /**
     * Get a human-readable reason label.
     */
    public function getResealReasonLabelAttribute(): string
    {
        return match ($this->reseal_reason) {
            self::REASON_INITIAL => 'Initial Seal',
            self::REASON_SCHEDULED => 'Scheduled Re-seal',
            self::REASON_ALGORITHM_UPGRADE => 'Algorithm Upgrade',
            self::REASON_CERTIFICATE_EXPIRY => 'Certificate Expiry Prevention',
            self::REASON_MANUAL => 'Manual Re-seal',
            default => ucfirst($this->reseal_reason),
        };
    }

    /**
     * Scope for entries in a specific chain.
     */
    public function scopeInChain($query, int $chainId)
    {
        return $query->where('tsa_chain_id', $chainId)->orderBy('sequence_number');
    }

    /**
     * Scope for initial entries only.
     */
    public function scopeInitial($query)
    {
        return $query->where('sequence_number', 0);
    }

    /**
     * Scope for reseal entries only.
     */
    public function scopeReseals($query)
    {
        return $query->where('sequence_number', '>', 0);
    }

    /**
     * Scope for entries with certificates expiring soon.
     */
    public function scopeExpiringSoon($query, int $daysAhead = 90)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($daysAhead));
    }

    /**
     * Scope for entries with expired certificates.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Verify the hash chain integrity against the previous entry.
     */
    public function verifyAgainstPrevious(?self $previous): bool
    {
        if ($this->isInitialEntry()) {
            // Initial entry has no previous
            return $this->previous_entry_hash === null;
        }

        if (! $previous) {
            return false;
        }

        // Verify the previous entry hash matches
        return $this->previous_entry_hash === $previous->cumulative_hash;
    }
}
