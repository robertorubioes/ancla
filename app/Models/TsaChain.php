<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * TsaChain model for managing TSA re-sealing chains.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_id
 * @property string $chain_type
 * @property string $preserved_hash
 * @property string $hash_algorithm
 * @property string $status
 * @property int $initial_tsa_token_id
 * @property Carbon $first_seal_at
 * @property Carbon $last_seal_at
 * @property int $seal_count
 * @property Carbon $next_seal_due_at
 * @property int $reseal_interval_days
 * @property int|null $last_reseal_tsa_id
 * @property Carbon|null $last_verified_at
 * @property string $verification_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TsaChain extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * Chain type constants.
     */
    public const TYPE_DOCUMENT = 'document';

    public const TYPE_EVIDENCE_PACKAGE = 'evidence_package';

    public const TYPE_AUDIT_TRAIL = 'audit_trail';

    /**
     * Chain status constants.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESEALING = 'resealing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BROKEN = 'broken';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_MIGRATED = 'migrated';

    /**
     * Verification status constants.
     */
    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VALID = 'valid';

    public const VERIFICATION_INVALID = 'invalid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_id',
        'chain_type',
        'preserved_hash',
        'hash_algorithm',
        'status',
        'initial_tsa_token_id',
        'first_seal_at',
        'last_seal_at',
        'seal_count',
        'next_seal_due_at',
        'reseal_interval_days',
        'last_reseal_tsa_id',
        'last_verified_at',
        'verification_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'first_seal_at' => 'datetime',
        'last_seal_at' => 'datetime',
        'next_seal_due_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'seal_count' => 'integer',
        'reseal_interval_days' => 'integer',
    ];

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the tenant that owns the chain.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the document this chain preserves.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the initial TSA token.
     */
    public function initialTsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class, 'initial_tsa_token_id');
    }

    /**
     * Get the last reseal TSA token.
     */
    public function lastResealTsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class, 'last_reseal_tsa_id');
    }

    /**
     * Get all entries in this chain.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TsaChainEntry::class)->orderBy('sequence_number');
    }

    /**
     * Get the latest entry in the chain.
     */
    public function latestEntry(): HasMany
    {
        return $this->hasMany(TsaChainEntry::class)
            ->orderByDesc('sequence_number')
            ->limit(1);
    }

    /**
     * Get the archived document.
     */
    public function archivedDocument(): BelongsTo
    {
        return $this->belongsTo(ArchivedDocument::class, 'document_id', 'document_id');
    }

    /**
     * Check if the chain is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the chain needs re-sealing.
     */
    public function needsReseal(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->next_seal_due_at->isPast() || $this->next_seal_due_at->isToday();
    }

    /**
     * Check if reseal is approaching.
     */
    public function isResealApproaching(int $daysAhead = 30): bool
    {
        if (! $this->next_seal_due_at) {
            return false;
        }

        return $this->next_seal_due_at->diffInDays(now()) <= $daysAhead;
    }

    /**
     * Check if the chain is verified as valid.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VALID;
    }

    /**
     * Check if verification is needed.
     */
    public function needsVerification(int $maxDaysOld = 7): bool
    {
        if (! $this->last_verified_at) {
            return true;
        }

        return $this->last_verified_at->diffInDays(now()) >= $maxDaysOld;
    }

    /**
     * Get the next sequence number for a new entry.
     */
    public function getNextSequenceNumber(): int
    {
        return $this->seal_count;
    }

    /**
     * Get the days until next seal is due.
     */
    public function getDaysUntilSealAttribute(): int
    {
        return max(0, (int) now()->diffInDays($this->next_seal_due_at, false));
    }

    /**
     * Get chain duration in days.
     */
    public function getChainDurationDaysAttribute(): int
    {
        return (int) $this->first_seal_at->diffInDays($this->last_seal_at);
    }

    /**
     * Scope for active chains.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for chains due for reseal.
     */
    public function scopeDueForReseal($query, int $daysAhead = 0)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('next_seal_due_at', '<=', now()->addDays($daysAhead));
    }

    /**
     * Scope for chains needing verification.
     */
    public function scopeNeedsVerification($query, int $maxDaysOld = 7)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) use ($maxDaysOld) {
                $q->whereNull('last_verified_at')
                    ->orWhere('last_verified_at', '<=', now()->subDays($maxDaysOld));
            });
    }

    /**
     * Scope by chain type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('chain_type', $type);
    }

    /**
     * Scope for invalid chains.
     */
    public function scopeInvalid($query)
    {
        return $query->whereIn('status', [self::STATUS_BROKEN, self::STATUS_EXPIRED])
            ->orWhere('verification_status', self::VERIFICATION_INVALID);
    }
}
