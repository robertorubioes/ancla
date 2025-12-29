<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * RetentionPolicy model for defining document retention rules.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string|null $document_type
 * @property int $retention_years
 * @property int $retention_days
 * @property int $archive_after_days
 * @property int|null $deep_archive_after_days
 * @property int $reseal_interval_days
 * @property int $reseal_before_expiry_days
 * @property bool $auto_delete_after_expiry
 * @property string $on_expiry_action
 * @property bool $require_pdfa_conversion
 * @property string $target_pdfa_version
 * @property bool $is_active
 * @property bool $is_default
 * @property int $priority
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RetentionPolicy extends Model
{
    use HasFactory;

    /**
     * Expiry action constants.
     */
    public const ACTION_ARCHIVE = 'archive';

    public const ACTION_DELETE = 'delete';

    public const ACTION_NOTIFY = 'notify';

    public const ACTION_EXTEND = 'extend';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'description',
        'document_type',
        'retention_years',
        'retention_days',
        'archive_after_days',
        'deep_archive_after_days',
        'reseal_interval_days',
        'reseal_before_expiry_days',
        'auto_delete_after_expiry',
        'on_expiry_action',
        'require_pdfa_conversion',
        'target_pdfa_version',
        'is_active',
        'is_default',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'retention_years' => 'integer',
        'retention_days' => 'integer',
        'archive_after_days' => 'integer',
        'deep_archive_after_days' => 'integer',
        'reseal_interval_days' => 'integer',
        'reseal_before_expiry_days' => 'integer',
        'auto_delete_after_expiry' => 'boolean',
        'require_pdfa_conversion' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the tenant that owns the policy.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all archived documents using this policy.
     */
    public function archivedDocuments(): HasMany
    {
        return $this->hasMany(ArchivedDocument::class);
    }

    /**
     * Check if this is a global policy (no tenant).
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * Check if this policy applies to all document types.
     */
    public function appliesToAllTypes(): bool
    {
        return $this->document_type === null;
    }

    /**
     * Check if this policy applies to a specific document type.
     */
    public function appliesToType(string $type): bool
    {
        return $this->document_type === null || $this->document_type === $type;
    }

    /**
     * Get the total retention period in days.
     */
    public function getTotalRetentionDaysAttribute(): int
    {
        return ($this->retention_years * 365) + $this->retention_days;
    }

    /**
     * Calculate the retention expiry date from a given date.
     */
    public function calculateExpiryDate(Carbon $fromDate): Carbon
    {
        return $fromDate->copy()
            ->addYears($this->retention_years)
            ->addDays($this->retention_days);
    }

    /**
     * Calculate the next reseal date from a given date.
     */
    public function calculateNextResealDate(Carbon $fromDate): Carbon
    {
        return $fromDate->copy()->addDays($this->reseal_interval_days);
    }

    /**
     * Calculate when to move to cold storage from a given date.
     */
    public function calculateColdStorageDate(Carbon $fromDate): Carbon
    {
        return $fromDate->copy()->addDays($this->archive_after_days);
    }

    /**
     * Calculate when to move to deep archive from a given date.
     */
    public function calculateDeepArchiveDate(Carbon $fromDate): ?Carbon
    {
        if (! $this->deep_archive_after_days) {
            return null;
        }

        return $fromDate->copy()->addDays($this->deep_archive_after_days);
    }

    /**
     * Get a human-readable retention period.
     */
    public function getRetentionPeriodLabelAttribute(): string
    {
        $parts = [];

        if ($this->retention_years > 0) {
            $parts[] = $this->retention_years.' '.($this->retention_years === 1 ? 'year' : 'years');
        }

        if ($this->retention_days > 0) {
            $parts[] = $this->retention_days.' '.($this->retention_days === 1 ? 'day' : 'days');
        }

        return implode(' and ', $parts) ?: '0 days';
    }

    /**
     * Get a human-readable expiry action.
     */
    public function getExpiryActionLabelAttribute(): string
    {
        return match ($this->on_expiry_action) {
            self::ACTION_ARCHIVE => 'Move to archive',
            self::ACTION_DELETE => 'Delete permanently',
            self::ACTION_NOTIFY => 'Notify administrators',
            self::ACTION_EXTEND => 'Extend retention period',
            default => ucfirst($this->on_expiry_action),
        };
    }

    /**
     * Scope for active policies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default policies.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for global policies (no tenant).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope for tenant-specific policies.
     */
    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id'); // Global policies

            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId); // Tenant-specific
            }
        });
    }

    /**
     * Scope for policies applicable to a document type.
     */
    public function scopeForDocumentType($query, ?string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->whereNull('document_type'); // All types

            if ($type) {
                $q->orWhere('document_type', $type);
            }
        });
    }

    /**
     * Scope ordered by priority (lowest first = highest priority).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Find the most applicable policy for a document.
     */
    public static function findApplicable(?int $tenantId, ?string $documentType = null): ?self
    {
        return self::query()
            ->active()
            ->forTenant($tenantId)
            ->forDocumentType($documentType)
            ->byPriority()
            ->first();
    }

    /**
     * Get the global default policy.
     */
    public static function getGlobalDefault(): ?self
    {
        return self::query()
            ->active()
            ->global()
            ->default()
            ->first();
    }
}
