<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Represents an immutable entry in the audit trail.
 *
 * Each entry is hashed and includes the hash of the previous entry,
 * creating a blockchain-like chain that makes tampering detectable.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $event_type
 * @property string $event_category
 * @property array|null $payload
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $hash
 * @property string|null $previous_hash
 * @property int $sequence
 * @property int|null $tsa_token_id
 * @property \Carbon\Carbon $created_at
 */
class AuditTrailEntry extends Model
{
    use BelongsToTenant;

    /**
     * Indicates if the model should be timestamped.
     * We only use created_at (entries are immutable).
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_trail_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'auditable_type',
        'auditable_id',
        'event_type',
        'event_category',
        'payload',
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
        'hash',
        'previous_hash',
        'sequence',
        'tsa_token_id',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'sequence' => 'integer',
        'actor_id' => 'integer',
        'tsa_token_id' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s.u',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AuditTrailEntry $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }

            if (empty($entry->created_at)) {
                $entry->created_at = now();
            }
        });
    }

    /**
     * Get the auditable model (polymorphic relation).
     *
     * @return MorphTo<Model, AuditTrailEntry>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the TSA token for this entry.
     *
     * @return BelongsTo<TsaToken, AuditTrailEntry>
     */
    public function tsaToken(): BelongsTo
    {
        return $this->belongsTo(TsaToken::class);
    }

    /**
     * Get the actor (user) who performed this action.
     *
     * @return BelongsTo<User, AuditTrailEntry>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Calculate hash for this entry's data.
     *
     * The hash is calculated from a deterministic JSON representation
     * of the entry data combined with the previous hash.
     *
     * @param  array<string, mixed>  $data  Entry data to hash
     * @param  string|null  $previousHash  Hash of the previous entry
     * @return string SHA-256 hash
     */
    public static function calculateHash(array $data, ?string $previousHash = null): string
    {
        // Normalize and sort data for deterministic hashing
        $hashData = [
            'previous_hash' => $previousHash ?? str_repeat('0', 64),
            'tenant_id' => $data['tenant_id'] ?? null,
            'auditable_type' => $data['auditable_type'] ?? null,
            'auditable_id' => $data['auditable_id'] ?? null,
            'event_type' => $data['event_type'] ?? null,
            'event_category' => $data['event_category'] ?? null,
            'payload' => $data['payload'] ?? null,
            'actor_type' => $data['actor_type'] ?? null,
            'actor_id' => $data['actor_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'sequence' => $data['sequence'] ?? null,
            'created_at' => $data['created_at'] ?? null,
        ];

        // Sort by keys for consistency
        ksort($hashData);

        // Create deterministic JSON
        $json = json_encode($hashData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json);
    }

    /**
     * Verify the integrity of this entry in the chain.
     *
     * @param  AuditTrailEntry|null  $previousEntry  The previous entry in the chain
     * @return bool True if the entry's hash is valid
     */
    public function verifyIntegrity(?AuditTrailEntry $previousEntry = null): bool
    {
        // Verify previous_hash matches actual previous entry
        $expectedPreviousHash = $previousEntry?->hash ?? str_repeat('0', 64);
        if ($this->previous_hash !== $expectedPreviousHash) {
            return false;
        }

        // Recalculate hash and compare
        $calculatedHash = self::calculateHash(
            $this->toArray(),
            $this->previous_hash
        );

        return hash_equals($this->hash, $calculatedHash);
    }

    /**
     * Check if this entry has a TSA timestamp.
     */
    public function hasTsaTimestamp(): bool
    {
        return $this->tsa_token_id !== null;
    }

    /**
     * Get a human-readable description of the event.
     */
    public function getDescriptionAttribute(): string
    {
        return match ($this->event_type) {
            'document.uploaded' => 'Document uploaded to system',
            'document.viewed' => 'Document viewed',
            'document.signed' => 'Document signed',
            'document.downloaded' => 'Document downloaded',
            'signature_process.created' => 'Signature process initiated',
            'signature_process.completed' => 'Signature process completed',
            'signer.invited' => 'Signer invited',
            'signer.signed' => 'Signer completed signature',
            'signer.rejected' => 'Signer rejected document',
            'evidence_package.generated' => 'Evidence package generated',
            default => $this->event_type,
        };
    }

    /**
     * Scope to filter by event type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by event category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope to filter entries with TSA timestamps.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>
     */
    public function scopeWithTsa($query)
    {
        return $query->whereNotNull('tsa_token_id');
    }

    /**
     * Scope to get entries for a specific auditable model.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AuditTrailEntry>
     */
    public function scopeForModel($query, Model $model)
    {
        return $query->where('auditable_type', get_class($model))
            ->where('auditable_id', $model->getKey());
    }
}
