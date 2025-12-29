<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\ArchivedDocument;
use App\Models\Document;
use App\Models\RetentionPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing document retention policies.
 */
class RetentionPolicyService
{
    /**
     * Get the applicable retention policy for a document.
     */
    public function getPolicyForDocument(Document $document): RetentionPolicy
    {
        // Try to find a tenant-specific policy first
        $policy = RetentionPolicy::findApplicable(
            $document->tenant_id,
            $document->document_type ?? null
        );

        // Fall back to global default if no specific policy found
        if (! $policy) {
            $policy = $this->getDefaultPolicy();
        }

        return $policy;
    }

    /**
     * Get the default retention policy for a tenant.
     */
    public function getDefaultPolicy(?int $tenantId = null): RetentionPolicy
    {
        // First try tenant default
        if ($tenantId) {
            $tenantDefault = RetentionPolicy::query()
                ->where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($tenantDefault) {
                return $tenantDefault;
            }
        }

        // Fall back to global default
        $globalDefault = RetentionPolicy::getGlobalDefault();

        if (! $globalDefault) {
            // Create a runtime default if none exists
            return $this->createRuntimeDefault();
        }

        return $globalDefault;
    }

    /**
     * Check if an archived document has expired according to its policy.
     */
    public function isExpired(ArchivedDocument $archived): bool
    {
        return $archived->retention_expires_at->isPast();
    }

    /**
     * Check if an archived document is expiring within the specified days.
     */
    public function isExpiringSoon(ArchivedDocument $archived, int $daysAhead = 90): bool
    {
        return ! $this->isExpired($archived)
            && $archived->retention_expires_at->diffInDays(now()) <= $daysAhead;
    }

    /**
     * Get documents with expired retention.
     */
    public function getExpiredDocuments(?int $tenantId = null): Collection
    {
        $query = ArchivedDocument::query()
            ->active()
            ->expiredRetention()
            ->with(['document', 'retentionPolicy']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Get documents expiring soon.
     */
    public function getExpiringDocuments(int $daysAhead = 90, ?int $tenantId = null): Collection
    {
        $query = ArchivedDocument::query()
            ->active()
            ->retentionExpiringSoon($daysAhead)
            ->with(['document', 'retentionPolicy']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->orderBy('retention_expires_at')->get();
    }

    /**
     * Extend the retention period for a document.
     */
    public function extendRetention(ArchivedDocument $archived, ?int $years = null, ?int $days = null): ArchivedDocument
    {
        $policy = $archived->retentionPolicy ?? $this->getDefaultPolicy($archived->tenant_id);

        $additionalYears = $years ?? $policy->retention_years;
        $additionalDays = $days ?? $policy->retention_days;

        $newExpiryDate = $archived->retention_expires_at
            ->addYears($additionalYears)
            ->addDays($additionalDays);

        // Validate against max retention
        $maxYears = config('archive.retention.max_years', 50);
        $maxDate = $archived->archived_at->addYears($maxYears);

        if ($newExpiryDate->gt($maxDate)) {
            $newExpiryDate = $maxDate;
        }

        $archived->update([
            'retention_expires_at' => $newExpiryDate,
            'metadata' => array_merge($archived->metadata ?? [], [
                'retention_extended_at' => now()->toIso8601String(),
                'previous_expiry' => $archived->retention_expires_at->toIso8601String(),
            ]),
        ]);

        Log::info('Retention extended', [
            'archived_document_id' => $archived->id,
            'new_expiry' => $newExpiryDate->toDateString(),
        ]);

        return $archived->fresh();
    }

    /**
     * Apply retention policy to a document when archiving.
     */
    public function applyPolicy(Document $document, ?RetentionPolicy $policy = null): array
    {
        $policy = $policy ?? $this->getPolicyForDocument($document);

        $archivedAt = now();

        return [
            'retention_policy_id' => $policy->id,
            'retention_expires_at' => $policy->calculateExpiryDate($archivedAt),
            'next_reseal_at' => $policy->calculateNextResealDate($archivedAt),
            'require_pdfa_conversion' => $policy->require_pdfa_conversion,
            'target_pdfa_version' => $policy->target_pdfa_version,
        ];
    }

    /**
     * Get retention statistics for a tenant.
     */
    public function getRetentionStats(?int $tenantId = null): array
    {
        $baseQuery = ArchivedDocument::query()->active();

        if ($tenantId) {
            $baseQuery->where('tenant_id', $tenantId);
        }

        $total = (clone $baseQuery)->count();
        $expired = (clone $baseQuery)->expiredRetention()->count();
        $expiringSoon30 = (clone $baseQuery)->retentionExpiringSoon(30)->count();
        $expiringSoon90 = (clone $baseQuery)->retentionExpiringSoon(90)->count();

        return [
            'total_archived' => $total,
            'expired' => $expired,
            'expiring_30_days' => $expiringSoon30,
            'expiring_90_days' => $expiringSoon90,
            'healthy' => $total - $expired - $expiringSoon90,
            'percentage_healthy' => $total > 0
                ? round((($total - $expired - $expiringSoon90) / $total) * 100, 2)
                : 100,
        ];
    }

    /**
     * Create a new retention policy.
     */
    public function createPolicy(array $data): RetentionPolicy
    {
        // If setting as default, remove default from others in same scope
        if (! empty($data['is_default'])) {
            RetentionPolicy::query()
                ->where('tenant_id', $data['tenant_id'] ?? null)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return RetentionPolicy::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => $data['tenant_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'retention_years' => $data['retention_years'] ?? config('archive.retention.default_years', 5),
            'retention_days' => $data['retention_days'] ?? 0,
            'archive_after_days' => $data['archive_after_days'] ?? 365,
            'deep_archive_after_days' => $data['deep_archive_after_days'] ?? null,
            'reseal_interval_days' => $data['reseal_interval_days'] ?? config('archive.reseal.default_interval_days', 365),
            'reseal_before_expiry_days' => $data['reseal_before_expiry_days'] ?? config('archive.reseal.reseal_before_expiry_days', 90),
            'auto_delete_after_expiry' => $data['auto_delete_after_expiry'] ?? false,
            'on_expiry_action' => $data['on_expiry_action'] ?? RetentionPolicy::ACTION_NOTIFY,
            'require_pdfa_conversion' => $data['require_pdfa_conversion'] ?? true,
            'target_pdfa_version' => $data['target_pdfa_version'] ?? 'PDF/A-3b',
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
            'priority' => $data['priority'] ?? 100,
        ]);
    }

    /**
     * Validate retention policy settings.
     */
    public function validatePolicy(array $data): array
    {
        $errors = [];

        $minYears = config('archive.retention.min_years', 1);
        $maxYears = config('archive.retention.max_years', 50);

        if (isset($data['retention_years'])) {
            if ($data['retention_years'] < $minYears) {
                $errors['retention_years'] = "Retention must be at least {$minYears} year(s)";
            }
            if ($data['retention_years'] > $maxYears) {
                $errors['retention_years'] = "Retention cannot exceed {$maxYears} years";
            }
        }

        if (isset($data['on_expiry_action'])) {
            $allowedActions = config('archive.retention.allowed_expiry_actions', []);
            if (! in_array($data['on_expiry_action'], $allowedActions)) {
                $errors['on_expiry_action'] = 'Invalid expiry action';
            }
        }

        return $errors;
    }

    /**
     * Process expiry actions for expired documents.
     */
    public function processExpiryActions(?int $tenantId = null): array
    {
        $expired = $this->getExpiredDocuments($tenantId);
        $results = [
            'processed' => 0,
            'archived' => 0,
            'deleted' => 0,
            'extended' => 0,
            'notified' => 0,
            'errors' => [],
        ];

        foreach ($expired as $archived) {
            try {
                $policy = $archived->retentionPolicy ?? $this->getDefaultPolicy($archived->tenant_id);
                $action = $policy->on_expiry_action;

                switch ($action) {
                    case RetentionPolicy::ACTION_ARCHIVE:
                        // Move to deep archive tier
                        $archived->update(['archive_tier' => ArchivedDocument::TIER_ARCHIVE]);
                        $results['archived']++;
                        break;

                    case RetentionPolicy::ACTION_DELETE:
                        if ($policy->auto_delete_after_expiry) {
                            $archived->update(['archive_status' => ArchivedDocument::STATUS_DELETED]);
                            $results['deleted']++;
                        }
                        break;

                    case RetentionPolicy::ACTION_EXTEND:
                        $this->extendRetention($archived);
                        $results['extended']++;
                        break;

                    case RetentionPolicy::ACTION_NOTIFY:
                    default:
                        // Just mark as notified (actual notification would be handled elsewhere)
                        $results['notified']++;
                        break;
                }

                $results['processed']++;

            } catch (\Exception $e) {
                $results['errors'][$archived->id] = $e->getMessage();
                Log::error('Expiry action failed', [
                    'archived_document_id' => $archived->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Create a runtime default policy (not persisted).
     */
    private function createRuntimeDefault(): RetentionPolicy
    {
        $policy = new RetentionPolicy;
        $policy->name = 'Runtime Default';
        $policy->retention_years = config('archive.retention.default_years', 5);
        $policy->retention_days = 0;
        $policy->archive_after_days = config('archive.tier_migration.hot_to_cold_days', 365);
        $policy->reseal_interval_days = config('archive.reseal.default_interval_days', 365);
        $policy->reseal_before_expiry_days = config('archive.reseal.reseal_before_expiry_days', 90);
        $policy->on_expiry_action = config('archive.retention.default_expiry_action', 'notify');
        $policy->require_pdfa_conversion = config('archive.format.auto_convert_pdfa', true);
        $policy->target_pdfa_version = config('archive.format.target_pdfa_version', 'PDF/A-3b');
        $policy->is_active = true;
        $policy->is_default = true;

        return $policy;
    }
}
