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
 * ArchivedDocument model for long-term document preservation.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_id
 * @property string $archive_tier
 * @property string $original_storage_path
 * @property string|null $archive_storage_path
 * @property string $storage_disk
 * @property string|null $storage_bucket
 * @property int|null $retention_policy_id
 * @property string $content_hash
 * @property string $hash_algorithm
 * @property string|null $archive_hash
 * @property string $format_version
 * @property string $current_format
 * @property string|null $pdfa_version
 * @property Carbon|null $format_migrated_at
 * @property Carbon $archived_at
 * @property Carbon|null $next_reseal_at
 * @property Carbon $retention_expires_at
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $last_accessed_at
 * @property int|null $initial_tsa_token_id
 * @property int|null $current_tsa_chain_id
 * @property int $reseal_count
 * @property string $archive_status
 * @property string|null $status_reason
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ArchivedDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * Archive tier constants.
     */
    public const TIER_HOT = 'hot';

    public const TIER_COLD = 'cold';

    public const TIER_ARCHIVE = 'archive';

    /**
     * Archive status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MIGRATING = 'migrating';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DELETED = 'deleted';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_id',
        'archive_tier',
        'original_storage_path',
        'archive_storage_path',
        'storage_disk',
        'storage_bucket',
        'retention_policy_id',
        'content_hash',
        'hash_algorithm',
        'archive_hash',
        'format_version',
        'current_format',
        'pdfa_version',
        'format_migrated_at',
        'archived_at',
        'next_reseal_at',
        'retention_expires_at',
        'last_verified_at',
        'last_accessed_at',
        'initial_tsa_token_id',
        'current_tsa_chain_id',
        'reseal_count',
        'archive_status',
        'status_reason',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'format_migrated_at' => 'datetime',
        'archived_at' => 'datetime',
        'next_reseal_at' => 'datetime',
        'retention_expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'reseal_count' => 'integer',
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
     * Get the tenant that owns the archived document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the original document.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the retention policy.
     */
    public function retentionPolicy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class);
    }

    /**
     * Get the initial TSA token.
     */
    public function initialTsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class, 'initial_tsa_token_id');
    }

    /**
     * Get the current TSA chain.
     */
    public function tsaChain(): BelongsTo
    {
        return $this->belongsTo(TsaChain::class, 'current_tsa_chain_id');
    }

    /**
     * Get all TSA chains for this document.
     */
    public function tsaChains(): HasMany
    {
        return $this->hasMany(TsaChain::class, 'document_id', 'document_id');
    }

    /**
     * Check if the document is in hot tier.
     */
    public function isHotTier(): bool
    {
        return $this->archive_tier === self::TIER_HOT;
    }

    /**
     * Check if the document is in cold tier.
     */
    public function isColdTier(): bool
    {
        return $this->archive_tier === self::TIER_COLD;
    }

    /**
     * Check if the document is in archive tier.
     */
    public function isArchiveTier(): bool
    {
        return $this->archive_tier === self::TIER_ARCHIVE;
    }

    /**
     * Check if the document needs re-sealing.
     */
    public function needsReseal(): bool
    {
        if (! $this->next_reseal_at) {
            return false;
        }

        return $this->next_reseal_at->isPast() || $this->next_reseal_at->isToday();
    }

    /**
     * Check if the document is approaching reseal date.
     */
    public function isResealApproaching(int $daysAhead = 30): bool
    {
        if (! $this->next_reseal_at) {
            return false;
        }

        return $this->next_reseal_at->diffInDays(now()) <= $daysAhead;
    }

    /**
     * Check if the retention period has expired.
     */
    public function isRetentionExpired(): bool
    {
        return $this->retention_expires_at->isPast();
    }

    /**
     * Check if the retention period is approaching expiry.
     */
    public function isRetentionExpiring(int $daysAhead = 90): bool
    {
        return $this->retention_expires_at->diffInDays(now()) <= $daysAhead;
    }

    /**
     * Check if the document is active.
     */
    public function isActive(): bool
    {
        return $this->archive_status === self::STATUS_ACTIVE;
    }

    /**
     * Get the days until retention expires.
     */
    public function getDaysUntilExpiryAttribute(): int
    {
        return max(0, (int) now()->diffInDays($this->retention_expires_at, false));
    }

    /**
     * Get the days until next reseal.
     */
    public function getDaysUntilResealAttribute(): ?int
    {
        if (! $this->next_reseal_at) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->next_reseal_at, false));
    }

    /**
     * Get the effective storage path.
     */
    public function getEffectiveStoragePathAttribute(): string
    {
        return $this->archive_storage_path ?? $this->original_storage_path;
    }

    /**
     * Scope for documents due for re-sealing.
     */
    public function scopeDueForReseal($query, int $daysAhead = 0)
    {
        return $query->where('archive_status', self::STATUS_ACTIVE)
            ->whereNotNull('next_reseal_at')
            ->where('next_reseal_at', '<=', now()->addDays($daysAhead));
    }

    /**
     * Scope for documents in a specific tier.
     */
    public function scopeInTier($query, string $tier)
    {
        return $query->where('archive_tier', $tier);
    }

    /**
     * Scope for active archived documents.
     */
    public function scopeActive($query)
    {
        return $query->where('archive_status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for documents ready for tier migration.
     */
    public function scopeReadyForTierMigration($query, string $fromTier, int $daysOld)
    {
        return $query->where('archive_tier', $fromTier)
            ->where('archive_status', self::STATUS_ACTIVE)
            ->where('archived_at', '<=', now()->subDays($daysOld));
    }

    /**
     * Scope for expired retention documents.
     */
    public function scopeExpiredRetention($query)
    {
        return $query->where('archive_status', self::STATUS_ACTIVE)
            ->where('retention_expires_at', '<', now());
    }

    /**
     * Scope for documents with retention expiring soon.
     */
    public function scopeRetentionExpiringSoon($query, int $daysAhead = 90)
    {
        return $query->where('archive_status', self::STATUS_ACTIVE)
            ->whereBetween('retention_expires_at', [now(), now()->addDays($daysAhead)]);
    }
}
