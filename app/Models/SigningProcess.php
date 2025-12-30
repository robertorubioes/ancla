<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Notification\SigningNotificationResult;
use App\Services\Notification\SigningNotificationService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SigningProcess model representing a signature request workflow.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $document_id
 * @property int $created_by
 * @property string $status
 * @property string $signature_order
 * @property string|null $custom_message
 * @property \Carbon\Carbon|null $deadline_at
 * @property \Carbon\Carbon|null $completed_at
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Document $document
 * @property-read User $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<Signer> $signers
 */
class SigningProcess extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'document_id',
        'created_by',
        'status',
        'signature_order',
        'custom_message',
        'deadline_at',
        'completed_at',
        'metadata',
        'final_document_path',
        'final_document_name',
        'final_document_hash',
        'final_document_size',
        'final_document_generated_at',
        'final_document_pages',
        'cancelled_by',
        'cancellation_reason',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'final_document_size' => 'integer',
        'final_document_generated_at' => 'datetime',
        'final_document_pages' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Signature order constants.
     */
    public const ORDER_SEQUENTIAL = 'sequential';

    public const ORDER_PARALLEL = 'parallel';

    /**
     * Get the tenant that owns the signing process.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the document being signed.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user who created the signing process.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the signers for this process.
     */
    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class)->orderBy('order');
    }

    /**
     * Get pending signers.
     */
    public function pendingSigners(): HasMany
    {
        return $this->hasMany(Signer::class)
            ->whereIn('status', [Signer::STATUS_PENDING, Signer::STATUS_SENT, Signer::STATUS_VIEWED])
            ->orderBy('order');
    }

    /**
     * Get completed signers.
     */
    public function completedSigners(): HasMany
    {
        return $this->hasMany(Signer::class)
            ->where('status', Signer::STATUS_SIGNED)
            ->orderBy('order');
    }

    /**
     * Get the audit trail entries for this process.
     */
    public function auditTrailEntries(): HasMany
    {
        return $this->hasMany(AuditTrailEntry::class, 'auditable_id')
            ->where('auditable_type', self::class)
            ->orderBy('sequence', 'asc');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get draft processes.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to get sent processes.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope to get in progress processes.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope to get completed processes.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get expired processes.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to get recent processes.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter processes expiring soon.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now()->addDays($days))
            ->where('deadline_at', '>', now())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_EXPIRED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if the process is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the process is sent.
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the process is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if the process is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the process is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if the process is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if the process has expired based on deadline.
     */
    public function hasExpired(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast();
    }

    /**
     * Check if signing order is sequential.
     */
    public function isSequential(): bool
    {
        return $this->signature_order === self::ORDER_SEQUENTIAL;
    }

    /**
     * Check if signing order is parallel.
     */
    public function isParallel(): bool
    {
        return $this->signature_order === self::ORDER_PARALLEL;
    }

    /**
     * Get the total number of signers.
     */
    public function getTotalSignersCount(): int
    {
        return $this->signers()->count();
    }

    /**
     * Get the number of completed signers.
     */
    public function getCompletedSignersCount(): int
    {
        return $this->signers()->where('status', Signer::STATUS_SIGNED)->count();
    }

    /**
     * Get completion percentage.
     */
    public function getCompletionPercentage(): int
    {
        $total = $this->getTotalSignersCount();
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->getCompletedSignersCount() / $total) * 100);
    }

    /**
     * Check if all signers have signed.
     */
    public function allSignersCompleted(): bool
    {
        return $this->getTotalSignersCount() > 0
            && $this->getTotalSignersCount() === $this->getCompletedSignersCount();
    }

    /**
     * Mark the process as sent.
     */
    public function markAsSent(): bool
    {
        return $this->update(['status' => self::STATUS_SENT]);
    }

    /**
     * Mark the process as in progress.
     */
    public function markAsInProgress(): bool
    {
        return $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    /**
     * Mark the process as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the process as expired.
     */
    public function markAsExpired(): bool
    {
        return $this->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Mark the process as cancelled.
     */
    public function markAsCancelled(): bool
    {
        return $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Cancel the signing process with reason and notifications.
     *
     * @param  int  $userId  User ID who is cancelling
     * @param  string  $reason  Reason for cancellation
     */
    public function cancel(int $userId, string $reason): bool
    {
        // Validate process can be cancelled
        if ($this->isCompleted() || $this->isCancelled()) {
            return false;
        }

        // Update process
        $result = $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_by' => $userId,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        if ($result) {
            // Invalidate all pending signer tokens
            $this->signers()
                ->whereIn('status', [Signer::STATUS_PENDING, Signer::STATUS_SENT, Signer::STATUS_VIEWED])
                ->update(['status' => 'cancelled']);

            // Send cancellation notifications (async)
            $this->sendCancellationNotifications();

            // Log audit trail
            if (method_exists($this, 'logAuditEvent')) {
                $this->logAuditEvent('signing_process.cancelled', [
                    'cancelled_by' => $userId,
                    'reason' => $reason,
                ]);
            }
        }

        return $result;
    }

    /**
     * Send cancellation notifications to pending signers.
     */
    private function sendCancellationNotifications(): void
    {
        $pendingSigners = $this->signers()
            ->where('status', 'cancelled')
            ->get();

        foreach ($pendingSigners as $signer) {
            try {
                \App\Jobs\SendCancellationNotificationJob::dispatch($this, $signer)
                    ->onQueue('notifications');
            } catch (\Exception $e) {
                Log::error('Failed to queue cancellation notification', [
                    'process_id' => $this->id,
                    'signer_id' => $signer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get the user who cancelled the process.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get the next signer in sequential order.
     */
    public function getNextSigner(): ?Signer
    {
        if (! $this->isSequential()) {
            return null;
        }

        return $this->signers()
            ->where('status', Signer::STATUS_PENDING)
            ->orderBy('order')
            ->first();
    }

    /**
     * Send signing request notifications to signers.
     *
     * This method queues email notifications for signers based on the
     * signing order (sequential or parallel) and updates the process status.
     *
     * @return SigningNotificationResult Result of the notification operation
     *
     * @throws \App\Services\Notification\SigningNotificationException
     */
    public function sendNotifications(): SigningNotificationResult
    {
        $service = app(SigningNotificationService::class);

        return $service->sendNotifications($this);
    }

    /**
     * Check if final document has been generated.
     */
    public function hasFinalDocument(): bool
    {
        return $this->final_document_path !== null;
    }

    /**
     * Get the full path to the final document.
     */
    public function getFinalDocumentPath(): ?string
    {
        if (! $this->hasFinalDocument()) {
            return null;
        }

        return storage_path('app/'.$this->final_document_path);
    }

    /**
     * Generate final document (consolidates all signed documents).
     *
     * This method is automatically called when the process is marked as completed.
     *
     *
     * @throws \App\Services\Document\FinalDocumentException
     */
    public function generateFinalDocument(): \App\Services\Document\FinalDocumentResult
    {
        $service = app(\App\Services\Document\FinalDocumentService::class);

        return $service->generateFinalDocument($this);
    }

    /**
     * Send copies of signed document to all signers.
     *
     * This method is automatically called after final document generation.
     *
     * @return \App\Services\Notification\CompletionNotificationResult Result of the notification operation
     *
     * @throws \App\Services\Notification\CompletionNotificationException
     */
    public function sendCopies(): \App\Services\Notification\CompletionNotificationResult
    {
        $service = app(\App\Services\Notification\CompletionNotificationService::class);

        return $service->sendCopies($this);
    }

    /**
     * Get route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
