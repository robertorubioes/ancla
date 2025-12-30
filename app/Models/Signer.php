<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Signer model representing an individual signer in a signing process.
 *
 * @property int $id
 * @property string $uuid
 * @property int $signing_process_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int $order
 * @property string $status
 * @property string $token
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $viewed_at
 * @property \Carbon\Carbon|null $signed_at
 * @property \Carbon\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read SigningProcess $signingProcess
 */
class Signer extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'signing_process_id',
        'name',
        'email',
        'phone',
        'order',
        'status',
        'token',
        'sent_at',
        'viewed_at',
        'signed_at',
        'rejected_at',
        'rejection_reason',
        'metadata',
        'signature_type',
        'signature_data',
        'evidence_package_id',
        'signature_metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order' => 'integer',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
        'signature_metadata' => 'array',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Boot method to set default values.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Signer $signer) {
            if (empty($signer->uuid)) {
                $signer->uuid = (string) Str::uuid();
            }
            if (empty($signer->token)) {
                $signer->token = Str::random(32);
            }
        });
    }

    /**
     * Get the signing process this signer belongs to.
     */
    public function signingProcess(): BelongsTo
    {
        return $this->belongsTo(SigningProcess::class);
    }

    /**
     * Get all OTP codes for this signer.
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    /**
     * Get the evidence package for the signature.
     */
    public function evidencePackage(): BelongsTo
    {
        return $this->belongsTo(EvidencePackage::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending signers.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get sent signers.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope to get viewed signers.
     */
    public function scopeViewed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VIEWED);
    }

    /**
     * Scope to get signed signers.
     */
    public function scopeSigned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SIGNED);
    }

    /**
     * Scope to get rejected signers.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter by email.
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to filter by signing process.
     */
    public function scopeByProcess(Builder $query, int $processId): Builder
    {
        return $query->where('signing_process_id', $processId);
    }

    /**
     * Scope to order by signing order.
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    /**
     * Check if the signer is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the signer has been sent the request.
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the signer has viewed the document.
     */
    public function hasViewed(): bool
    {
        return $this->status === self::STATUS_VIEWED;
    }

    /**
     * Check if the signer has signed.
     */
    public function hasSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    /**
     * Check if the signer has rejected.
     */
    public function hasRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the signer can sign now.
     */
    public function canSignNow(): bool
    {
        // If parallel, can always sign if not already signed
        if ($this->signingProcess->isParallel()) {
            return in_array($this->status, [self::STATUS_SENT, self::STATUS_VIEWED]);
        }

        // If sequential, check if previous signers have signed
        if ($this->signingProcess->isSequential()) {
            if (! in_array($this->status, [self::STATUS_SENT, self::STATUS_VIEWED])) {
                return false;
            }

            // Check if all previous signers have signed
            $previousSigners = $this->signingProcess->signers()
                ->where('order', '<', $this->order)
                ->get();

            foreach ($previousSigners as $previousSigner) {
                if (! $previousSigner->hasSigned()) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Mark the signer as sent.
     */
    public function markAsSent(): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the signer as viewed.
     */
    public function markAsViewed(): bool
    {
        $data = ['status' => self::STATUS_VIEWED];

        if ($this->viewed_at === null) {
            $data['viewed_at'] = now();
        }

        return $this->update($data);
    }

    /**
     * Mark the signer as signed.
     */
    public function markAsSigned(): bool
    {
        return $this->update([
            'status' => self::STATUS_SIGNED,
            'signed_at' => now(),
        ]);
    }

    /**
     * Mark the signer as rejected.
     */
    public function markAsRejected(?string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Get the signing URL for this signer.
     */
    public function getSigningUrl(): string
    {
        return route('sign.show', ['token' => $this->token]);
    }

    /**
     * Check if the signer's deadline has passed.
     */
    public function isExpired(): bool
    {
        $deadline = $this->signingProcess->deadline_at;

        return $deadline !== null && $deadline->isPast();
    }

    /**
     * Regenerate the signing token.
     */
    public function regenerateToken(): bool
    {
        return $this->update(['token' => Str::random(32)]);
    }

    /**
     * Get route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
