<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Represents a complete evidence package for legal verification.
 *
 * An evidence package contains all proof materials for a signed document:
 * - Original document hash
 * - Audit trail with chained hashes
 * - TSA tokens for qualified timestamps
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $packagable_type
 * @property int $packagable_id
 * @property string $document_hash
 * @property string $audit_trail_hash
 * @property int|null $tsa_token_id
 * @property string $status
 * @property \Carbon\Carbon|null $generated_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EvidencePackage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * Status enum values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'packagable_type',
        'packagable_id',
        'document_hash',
        'audit_trail_hash',
        'tsa_token_id',
        'status',
        'generated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (EvidencePackage $package) {
            if (empty($package->uuid)) {
                $package->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the packagable model (polymorphic relation).
     *
     * @return MorphTo<Model, EvidencePackage>
     */
    public function packagable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the TSA token for this package.
     *
     * @return BelongsTo<TsaToken, EvidencePackage>
     */
    public function tsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class);
    }

    /**
     * Check if the package is ready for download.
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Check if the package is being generated.
     */
    public function isGenerating(): bool
    {
        return $this->status === self::STATUS_GENERATING;
    }

    /**
     * Check if the package generation failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the package has expired.
     */
    public function hasExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Mark the package as generating.
     */
    public function markAsGenerating(): void
    {
        $this->update(['status' => self::STATUS_GENERATING]);
    }

    /**
     * Mark the package as ready.
     */
    public function markAsReady(): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'generated_at' => now(),
        ]);
    }

    /**
     * Mark the package as failed.
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    /**
     * Verify the integrity of the package.
     *
     * Checks that:
     * - Document hash matches
     * - Audit trail hash matches
     * - TSA token is valid (if present)
     *
     * @param  string  $currentDocumentHash  Current hash of the document
     * @param  string  $currentAuditTrailHash  Current hash of the audit trail
     */
    public function verifyIntegrity(string $currentDocumentHash, string $currentAuditTrailHash): bool
    {
        // Verify document hash
        if (! hash_equals($this->document_hash, $currentDocumentHash)) {
            return false;
        }

        // Verify audit trail hash
        if (! hash_equals($this->audit_trail_hash, $currentAuditTrailHash)) {
            return false;
        }

        // Verify TSA token if present
        if ($this->tsaToken && ! $this->tsaToken->isValid()) {
            return false;
        }

        return true;
    }

    /**
     * Scope to filter ready packages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<EvidencePackage>  $query
     * @return \Illuminate\Database\Eloquent\Builder<EvidencePackage>
     */
    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    /**
     * Scope to filter pending packages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<EvidencePackage>  $query
     * @return \Illuminate\Database\Eloquent\Builder<EvidencePackage>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get packages for a specific model.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<EvidencePackage>  $query
     * @return \Illuminate\Database\Eloquent\Builder<EvidencePackage>
     */
    public function scopeForModel($query, Model $model)
    {
        return $query->where('packagable_type', get_class($model))
            ->where('packagable_id', $model->getKey());
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
            self::STATUS_GENERATING,
            self::STATUS_READY,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
        ];
    }
}
