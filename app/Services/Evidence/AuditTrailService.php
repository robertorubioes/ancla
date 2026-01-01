<?php

namespace App\Services\Evidence;

use App\Models\AuditTrailEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service for managing the immutable audit trail.
 *
 * Creates blockchain-like chained entries where each entry includes
 * the hash of the previous entry, making tampering detectable.
 *
 * Critical events also receive TSA timestamps for qualified proof.
 *
 * @see ADR-005 in docs/architecture/decisions.md
 */
class AuditTrailService
{
    /**
     * Genesis hash (used as previous_hash for first entry).
     */
    private const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Create a new AuditTrailService instance.
     */
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService
    ) {}

    /**
     * Record an event in the audit trail.
     *
     * @param  Model  $auditable  The model being audited
     * @param  string  $event  Event type (e.g., 'document.uploaded')
     * @param  array<string, mixed>  $payload  Additional event data
     * @return AuditTrailEntry The created entry
     */
    public function record(Model $auditable, string $event, array $payload = []): AuditTrailEntry
    {
        return DB::transaction(function () use ($auditable, $event, $payload) {
            // Get tenant_id: first try container, then from auditable model
            $tenant = app()->bound('tenant') ? app('tenant') : null;
            $tenantId = $tenant?->id;

            // Fallback: get tenant_id from the auditable model or its relations
            if ($tenantId === null) {
                $tenantId = $this->resolveTenantIdFromModel($auditable);
            }

            // Get the last entry to chain to (lock for update to prevent race conditions)
            $lastEntry = $this->getLastEntry($auditable);

            // Calculate sequence number
            $sequence = $lastEntry ? $lastEntry->sequence + 1 : 1;

            // Get previous hash (genesis hash if first entry)
            $previousHash = $lastEntry?->hash ?? self::GENESIS_HASH;

            // Prepare entry data
            $entryData = [
                'tenant_id' => $tenantId,
                'auditable_type' => get_class($auditable),
                'auditable_id' => $auditable->getKey(),
                'event_type' => $event,
                'event_category' => $this->getEventCategory($event),
                'payload' => $payload,
                'actor_type' => $this->resolveActorType(),
                'actor_id' => auth()->id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'sequence' => $sequence,
                'created_at' => now()->format('Y-m-d H:i:s.u'),
            ];

            // Calculate hash for this entry
            $hash = $this->calculateEntryHash($entryData, $previousHash);

            // Create the entry
            $entry = AuditTrailEntry::create([
                'uuid' => Str::uuid(),
                'tenant_id' => $tenantId,
                'auditable_type' => $entryData['auditable_type'],
                'auditable_id' => $entryData['auditable_id'],
                'event_type' => $event,
                'event_category' => $entryData['event_category'],
                'payload' => $payload,
                'actor_type' => $entryData['actor_type'],
                'actor_id' => $entryData['actor_id'],
                'ip_address' => $entryData['ip_address'],
                'user_agent' => $entryData['user_agent'],
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'sequence' => $sequence,
                'created_at' => now(),
            ]);

            // Request TSA timestamp for critical events
            if ($this->requiresTsa($event)) {
                $tsaToken = $this->tsaService->requestTimestamp($hash, $tenantId);
                $entry->update(['tsa_token_id' => $tsaToken->id]);
            }

            return $entry->fresh();
        });
    }

    /**
     * Log an event without requiring a specific model.
     * This is a convenience method for logging events that may not have a direct model association.
     *
     * @param  string  $eventType  Event type (e.g., 'signing_process.created')
     * @param  array<string, mixed>  $metadata  Additional event data
     * @param  int|null  $userId  The user ID performing the action
     * @param  int|null  $tenantId  The tenant ID
     */
    public function logEvent(
        string $eventType,
        array $metadata = [],
        ?int $userId = null,
        ?int $tenantId = null
    ): void {
        \Illuminate\Support\Facades\Log::info("Audit: {$eventType}", [
            'event_type' => $eventType,
            'metadata' => $metadata,
            'user_id' => $userId ?? auth()->id(),
            'tenant_id' => $tenantId ?? (app()->bound('tenant') ? app('tenant')?->id : null),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Verify the integrity of the audit trail chain for a model.
     *
     * @param  string  $auditableType  The model class name
     * @param  int|string  $auditableId  The model ID
     * @return ChainVerificationResult Result of the verification
     */
    public function verifyChain(string $auditableType, int|string $auditableId): ChainVerificationResult
    {
        $entries = AuditTrailEntry::where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->orderBy('sequence', 'asc')
            ->get();

        if ($entries->isEmpty()) {
            return new ChainVerificationResult(
                valid: true,
                entriesVerified: 0,
                errors: []
            );
        }

        $errors = [];
        $previousEntry = null;

        foreach ($entries as $entry) {
            // Verify sequence continuity
            $expectedSequence = $previousEntry ? $previousEntry->sequence + 1 : 1;
            if ($entry->sequence !== $expectedSequence) {
                $errors[] = "Entry {$entry->id}: Sequence gap detected (expected {$expectedSequence}, got {$entry->sequence})";
            }

            // Verify previous_hash matches
            $expectedPreviousHash = $previousEntry?->hash ?? self::GENESIS_HASH;
            if ($entry->previous_hash !== $expectedPreviousHash) {
                $errors[] = "Entry {$entry->id}: Previous hash mismatch (chain broken)";
            }

            // Verify entry hash is correct
            $calculatedHash = $this->calculateEntryHash([
                'tenant_id' => $entry->tenant_id,
                'auditable_type' => $entry->auditable_type,
                'auditable_id' => $entry->auditable_id,
                'event_type' => $entry->event_type,
                'event_category' => $entry->event_category,
                'payload' => $entry->payload,
                'actor_type' => $entry->actor_type,
                'actor_id' => $entry->actor_id,
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
                'sequence' => $entry->sequence,
                'created_at' => $entry->created_at->format('Y-m-d H:i:s.u'),
            ], $entry->previous_hash);

            if (! hash_equals($entry->hash, $calculatedHash)) {
                $errors[] = "Entry {$entry->id}: Hash mismatch (data tampered)";
            }

            $previousEntry = $entry;
        }

        return new ChainVerificationResult(
            valid: empty($errors),
            entriesVerified: $entries->count(),
            errors: $errors,
            firstSequence: $entries->first()?->sequence,
            lastSequence: $entries->last()?->sequence
        );
    }

    /**
     * Get the audit trail for a model.
     *
     * @param  Model  $auditable  The model
     * @return Collection<int, AuditTrailEntry> Collection of entries
     */
    public function getTrailFor(Model $auditable): Collection
    {
        return AuditTrailEntry::forModel($auditable)
            ->orderBy('sequence', 'asc')
            ->get();
    }

    /**
     * Get the last audit trail entry for a model.
     *
     * @param  Model  $auditable  The model
     * @return AuditTrailEntry|null Last entry or null
     */
    public function getLastEntry(Model $auditable): ?AuditTrailEntry
    {
        return AuditTrailEntry::forModel($auditable)
            ->orderBy('sequence', 'desc')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Calculate the hash for an audit trail entry.
     *
     * @param  array<string, mixed>  $data  Entry data
     * @param  string|null  $previousHash  Hash of previous entry
     * @return string SHA-256 hash
     */
    public function calculateEntryHash(array $data, ?string $previousHash = null): string
    {
        // Build the data to hash
        $hashData = [
            'previous_hash' => $previousHash ?? self::GENESIS_HASH,
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

        return $this->hashingService->hashData($hashData);
    }

    /**
     * Determine the category of an event.
     *
     * @param  string  $eventType  Event type
     * @return string Category (document, signature, access, system)
     */
    private function getEventCategory(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'document.') => 'document',
            str_starts_with($eventType, 'signature') || str_starts_with($eventType, 'signer') => 'signature',
            str_starts_with($eventType, 'access.') || str_starts_with($eventType, 'login') => 'access',
            default => 'system',
        };
    }

    /**
     * Resolve the actor type from the current context.
     *
     * @return string Actor type (user, signer, system, api)
     */
    private function resolveActorType(): string
    {
        if (! auth()->check()) {
            return 'system';
        }

        if (request()?->is('api/*')) {
            return 'api';
        }

        if (session()->has('signer_token')) {
            return 'signer';
        }

        return 'user';
    }

    /**
     * Check if an event requires a TSA timestamp.
     *
     * @param  string  $eventType  Event type
     * @return bool True if TSA is required
     */
    private function requiresTsa(string $eventType): bool
    {
        $tsaRequiredEvents = config('evidence.audit.tsa_required_events', []);

        return in_array($eventType, $tsaRequiredEvents);
    }

    /**
     * Get the genesis hash constant.
     *
     * @return string The genesis hash (64 zeros)
     */
    public function getGenesisHash(): string
    {
        return self::GENESIS_HASH;
    }

    /**
     * Resolve tenant_id from the auditable model.
     *
     * Tries to get tenant_id directly from the model, or from related models
     * (e.g., SigningProcess from Signer, or Document from SigningProcess).
     *
     * @param  Model  $auditable  The model being audited
     * @return int|null The tenant_id or null
     */
    private function resolveTenantIdFromModel(Model $auditable): ?int
    {
        // Direct tenant_id attribute
        if (isset($auditable->tenant_id)) {
            return $auditable->tenant_id;
        }

        // Try common relationships that have tenant_id
        $relationships = ['signingProcess', 'document', 'tenant'];

        foreach ($relationships as $relation) {
            if (method_exists($auditable, $relation)) {
                $related = $auditable->$relation;
                if ($related && isset($related->tenant_id)) {
                    return $related->tenant_id;
                }
            }
        }

        return null;
    }
}
