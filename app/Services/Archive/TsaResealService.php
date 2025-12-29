<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\Document;
use App\Models\TsaChain;
use App\Models\TsaChainEntry;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing TSA re-sealing chains.
 *
 * Implements the re-sealing formula:
 * Re-seal N = TSA(Hash(Document + TSA₀ + TSA₁ + ... + TSAₙ₋₁))
 */
class TsaResealService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaService $tsaService
    ) {}

    /**
     * Initialize a new TSA chain for a document.
     */
    public function initializeChain(Document $document, string $chainType = TsaChain::TYPE_DOCUMENT): TsaChain
    {
        return DB::transaction(function () use ($document, $chainType) {
            // Get the document hash to preserve
            $preservedHash = $document->content_hash;

            // Request initial TSA timestamp
            $initialToken = $this->tsaService->requestTimestamp($preservedHash);

            // Calculate expiry date (if available from TSA token)
            $expiresAt = $initialToken->expires_at ?? now()->addYears(2);

            // Create the chain
            $chain = TsaChain::create([
                'uuid' => Str::uuid()->toString(),
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'chain_type' => $chainType,
                'preserved_hash' => $preservedHash,
                'hash_algorithm' => 'SHA-256',
                'status' => TsaChain::STATUS_ACTIVE,
                'initial_tsa_token_id' => $initialToken->id,
                'first_seal_at' => $initialToken->issued_at ?? now(),
                'last_seal_at' => $initialToken->issued_at ?? now(),
                'seal_count' => 1,
                'next_seal_due_at' => $this->calculateNextResealDate($expiresAt),
                'reseal_interval_days' => config('archive.reseal.default_interval_days', 365),
                'last_verified_at' => now(),
                'verification_status' => TsaChain::VERIFICATION_VALID,
            ]);

            // Create the initial entry (sequence 0)
            TsaChainEntry::create([
                'uuid' => Str::uuid()->toString(),
                'tsa_chain_id' => $chain->id,
                'sequence_number' => 0,
                'tsa_token_id' => $initialToken->id,
                'previous_entry_hash' => null,
                'cumulative_hash' => $preservedHash,
                'sealed_hash' => $preservedHash,
                'reseal_reason' => TsaChainEntry::REASON_INITIAL,
                'tsa_provider' => $initialToken->provider ?? 'default',
                'algorithm_used' => 'SHA-256',
                'timestamp_value' => $initialToken->issued_at ?? now(),
                'sealed_at' => $initialToken->issued_at ?? now(),
                'expires_at' => $expiresAt,
                'previous_entry_id' => null,
                'created_at' => now(),
            ]);

            Log::info('TSA chain initialized', [
                'chain_id' => $chain->id,
                'document_id' => $document->id,
                'preserved_hash' => $preservedHash,
            ]);

            return $chain;
        });
    }

    /**
     * Perform a re-seal operation on a chain.
     */
    public function reseal(TsaChain $chain, string $reason = TsaChainEntry::REASON_SCHEDULED): TsaChainEntry
    {
        return DB::transaction(function () use ($chain, $reason) {
            // Mark chain as resealing
            $chain->update(['status' => TsaChain::STATUS_RESEALING]);

            try {
                // Get the latest entry
                $latestEntry = $chain->entries()->orderByDesc('sequence_number')->first();

                if (! $latestEntry) {
                    throw new \RuntimeException('Chain has no entries');
                }

                // Calculate the cumulative hash for the new seal
                $dataToSeal = $this->calculateCumulativeHash($chain);

                // Request new TSA timestamp
                $newToken = $this->tsaService->requestTimestamp($dataToSeal);

                // Calculate expiry
                $expiresAt = $newToken->expires_at ?? now()->addYears(2);

                // Create new entry
                $newEntry = TsaChainEntry::create([
                    'uuid' => Str::uuid()->toString(),
                    'tsa_chain_id' => $chain->id,
                    'sequence_number' => $latestEntry->sequence_number + 1,
                    'tsa_token_id' => $newToken->id,
                    'previous_entry_hash' => $latestEntry->cumulative_hash,
                    'cumulative_hash' => $dataToSeal,
                    'sealed_hash' => $dataToSeal,
                    'reseal_reason' => $reason,
                    'tsa_provider' => $newToken->provider ?? 'default',
                    'algorithm_used' => 'SHA-256',
                    'timestamp_value' => $newToken->issued_at ?? now(),
                    'sealed_at' => $newToken->issued_at ?? now(),
                    'expires_at' => $expiresAt,
                    'previous_entry_id' => $latestEntry->id,
                    'created_at' => now(),
                ]);

                // Update chain
                $chain->update([
                    'status' => TsaChain::STATUS_ACTIVE,
                    'last_seal_at' => $newEntry->sealed_at,
                    'seal_count' => $chain->seal_count + 1,
                    'next_seal_due_at' => $this->calculateNextResealDate($expiresAt),
                    'last_reseal_tsa_id' => $newToken->id,
                    'last_verified_at' => now(),
                    'verification_status' => TsaChain::VERIFICATION_VALID,
                ]);

                Log::info('TSA chain re-sealed', [
                    'chain_id' => $chain->id,
                    'sequence_number' => $newEntry->sequence_number,
                    'reason' => $reason,
                ]);

                return $newEntry;

            } catch (\Exception $e) {
                // Revert to active status on failure
                $chain->update(['status' => TsaChain::STATUS_ACTIVE]);
                throw $e;
            }
        });
    }

    /**
     * Verify the integrity of a TSA chain.
     */
    public function verifyChain(TsaChain $chain): ChainVerificationResult
    {
        $entries = $chain->entries()->orderBy('sequence_number')->with('tsaToken')->get();
        $errors = [];
        $warnings = [];
        $previousEntry = null;

        foreach ($entries as $entry) {
            // Verify sequence continuity
            if ($previousEntry !== null) {
                $expectedSequence = $previousEntry->sequence_number + 1;
                if ($entry->sequence_number !== $expectedSequence) {
                    $errors[] = "Sequence gap at entry {$entry->sequence_number}, expected {$expectedSequence}";
                }

                // Verify previous entry reference
                if ($entry->previous_entry_id !== $previousEntry->id) {
                    $errors[] = "Previous entry mismatch at sequence {$entry->sequence_number}";
                }

                // Verify hash chain
                if ($entry->previous_entry_hash !== $previousEntry->cumulative_hash) {
                    $errors[] = "Hash chain broken at sequence {$entry->sequence_number}";
                }
            } else {
                // First entry validations
                if ($entry->sequence_number !== 0) {
                    $errors[] = 'First entry sequence is not 0';
                }
                if ($entry->previous_entry_hash !== null) {
                    $errors[] = 'Initial entry should not have previous hash';
                }
            }

            // Verify TSA token exists
            if (! $entry->tsaToken) {
                $errors[] = "Missing TSA token for entry {$entry->sequence_number}";
            } else {
                // Verify TSA token
                if (! $this->tsaService->verifyTimestamp($entry->tsaToken)) {
                    $errors[] = "TSA token verification failed for entry {$entry->sequence_number}";
                }
            }

            // Check for expiring certificates
            if ($entry->isExpiringSoon(90)) {
                $warnings[] = "Certificate expiring soon for entry {$entry->sequence_number}";
            }

            $previousEntry = $entry;
        }

        // Verify preserved hash matches document
        $document = $chain->document;
        if ($document && $chain->preserved_hash !== $document->content_hash) {
            $errors[] = 'Preserved hash does not match current document hash';
        }

        $isValid = empty($errors);

        // Update chain verification status
        $chain->update([
            'last_verified_at' => now(),
            'verification_status' => $isValid
                ? TsaChain::VERIFICATION_VALID
                : TsaChain::VERIFICATION_INVALID,
        ]);

        return new ChainVerificationResult(
            isValid: $isValid,
            entriesVerified: $entries->count(),
            errors: $errors,
            warnings: $warnings,
            firstEntry: $entries->first()?->toArray(),
            lastEntry: $entries->last()?->toArray()
        );
    }

    /**
     * Calculate the cumulative hash for the chain.
     *
     * Formula: Hash(Document + TSA₀ + TSA₁ + ... + TSAₙ₋₁)
     */
    public function calculateCumulativeHash(TsaChain $chain): string
    {
        $entries = $chain->entries()->orderBy('sequence_number')->get();

        // Build the data string
        $dataComponents = [
            $chain->preserved_hash, // Document hash
        ];

        foreach ($entries as $entry) {
            $dataComponents[] = implode('|', [
                $entry->sealed_hash,
                $entry->tsa_token_id,
                $entry->timestamp_value->toIso8601String(),
                $entry->sequence_number,
            ]);
        }

        return $this->hashingService->hashString(implode('|', $dataComponents));
    }

    /**
     * Get the next required re-seal date.
     */
    public function getNextResealDate(TsaChain $chain): \Carbon\Carbon
    {
        $latestEntry = $chain->entries()->orderByDesc('sequence_number')->first();

        if (! $latestEntry || ! $latestEntry->expires_at) {
            return now()->addDays($chain->reseal_interval_days);
        }

        return $this->calculateNextResealDate($latestEntry->expires_at);
    }

    /**
     * Get chains due for re-sealing.
     */
    public function getChainsDueForReseal(int $daysAhead = 30): Collection
    {
        return TsaChain::query()
            ->active()
            ->dueForReseal($daysAhead)
            ->with(['document', 'entries'])
            ->get();
    }

    /**
     * Schedule all due re-seals and process them.
     */
    public function processAllDueReseals(): array
    {
        $dueChains = $this->getChainsDueForReseal();
        $results = [
            'total' => $dueChains->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($dueChains as $chain) {
            try {
                $this->reseal($chain);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$chain->id] = $e->getMessage();

                Log::error('Reseal failed for chain', [
                    'chain_id' => $chain->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get chains needing verification.
     */
    public function getChainsNeedingVerification(int $maxDaysOld = 7): Collection
    {
        return TsaChain::query()
            ->active()
            ->needsVerification($maxDaysOld)
            ->with(['document', 'entries'])
            ->get();
    }

    /**
     * Verify all chains that need verification.
     */
    public function verifyAllChains(): array
    {
        $chains = $this->getChainsNeedingVerification();
        $results = [
            'total' => $chains->count(),
            'valid' => 0,
            'invalid' => 0,
            'errors' => [],
        ];

        foreach ($chains as $chain) {
            try {
                $verification = $this->verifyChain($chain);
                if ($verification->isValid) {
                    $results['valid']++;
                } else {
                    $results['invalid']++;
                    $results['errors'][$chain->id] = $verification->errors;
                }
            } catch (\Exception $e) {
                $results['invalid']++;
                $results['errors'][$chain->id] = [$e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Calculate the next re-seal date based on certificate expiry.
     */
    private function calculateNextResealDate(\Carbon\Carbon $expiresAt): \Carbon\Carbon
    {
        $beforeExpiryDays = config('archive.reseal.reseal_before_expiry_days', 90);
        $maxIntervalDays = config('archive.reseal.default_interval_days', 365);

        // Re-seal before expiry
        $resealDate = $expiresAt->copy()->subDays($beforeExpiryDays);

        // But don't exceed max interval from now
        $maxDate = now()->addDays($maxIntervalDays);

        return $resealDate->lt($maxDate) ? $resealDate : $maxDate;
    }
}
