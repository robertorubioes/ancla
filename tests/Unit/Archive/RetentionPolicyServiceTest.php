<?php

declare(strict_types=1);

namespace Tests\Unit\Archive;

use App\Models\ArchivedDocument;
use App\Models\Document;
use App\Models\RetentionPolicy;
use App\Models\Tenant;
use App\Services\Archive\RetentionPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private RetentionPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RetentionPolicyService;
    }

    public function test_get_policy_for_document_returns_tenant_specific_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        // Create global policy
        RetentionPolicy::factory()->global()->default()->create([
            'name' => 'Global Default',
            'retention_years' => 5,
        ]);

        // Create tenant-specific policy
        $tenantPolicy = RetentionPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Policy',
            'retention_years' => 10,
            'priority' => 50,
        ]);

        $policy = $this->service->getPolicyForDocument($document);

        $this->assertEquals($tenantPolicy->id, $policy->id);
        $this->assertEquals(10, $policy->retention_years);
    }

    public function test_get_policy_for_document_falls_back_to_global(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        // Create only global policy
        $globalPolicy = RetentionPolicy::factory()->global()->default()->create([
            'name' => 'Global Default',
            'retention_years' => 5,
        ]);

        $policy = $this->service->getPolicyForDocument($document);

        $this->assertEquals($globalPolicy->id, $policy->id);
        $this->assertEquals(5, $policy->retention_years);
    }

    public function test_get_default_policy_returns_tenant_default_first(): void
    {
        $tenant = Tenant::factory()->create();

        // Create global default
        RetentionPolicy::factory()->global()->default()->create([
            'name' => 'Global Default',
        ]);

        // Create tenant default
        $tenantDefault = RetentionPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'is_default' => true,
            'name' => 'Tenant Default',
        ]);

        $policy = $this->service->getDefaultPolicy($tenant->id);

        $this->assertEquals($tenantDefault->id, $policy->id);
    }

    public function test_is_expired_returns_true_for_expired_document(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'retention_expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($this->service->isExpired($archived));
    }

    public function test_is_expired_returns_false_for_active_document(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'retention_expires_at' => now()->addYear(),
        ]);

        $this->assertFalse($this->service->isExpired($archived));
    }

    public function test_is_expiring_soon_detects_upcoming_expiry(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'retention_expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($this->service->isExpiringSoon($archived, 90));
        $this->assertFalse($this->service->isExpiringSoon($archived, 10));
    }

    public function test_get_expired_documents_returns_only_expired(): void
    {
        $tenant = Tenant::factory()->create();

        // Create expired document
        $expiredDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $expiredDoc->id,
            'retention_expires_at' => now()->subDays(10),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        // Create active document
        $activeDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $activeDoc->id,
            'retention_expires_at' => now()->addYears(5),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        $expired = $this->service->getExpiredDocuments($tenant->id);

        $this->assertCount(1, $expired);
        $this->assertEquals($expiredDoc->id, $expired->first()->document_id);
    }

    public function test_get_expiring_documents_returns_documents_within_timeframe(): void
    {
        $tenant = Tenant::factory()->create();

        // Create document expiring in 30 days
        $soonDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $soonDoc->id,
            'retention_expires_at' => now()->addDays(30),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        // Create document expiring in 1 year
        $laterDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $laterDoc->id,
            'retention_expires_at' => now()->addYear(),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        $expiring = $this->service->getExpiringDocuments(60, $tenant->id);

        $this->assertCount(1, $expiring);
        $this->assertEquals($soonDoc->id, $expiring->first()->document_id);
    }

    public function test_extend_retention_updates_expiry_date(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $originalExpiry = now()->addYears(5);
        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'retention_expires_at' => $originalExpiry,
        ]);

        $updated = $this->service->extendRetention($archived, 5, 0);

        $this->assertTrue($updated->retention_expires_at->gt($originalExpiry));
        $this->assertEquals($originalExpiry->addYears(5)->toDateString(), $updated->retention_expires_at->toDateString());
    }

    public function test_apply_policy_calculates_correct_dates(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $policy = RetentionPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'retention_years' => 7,
            'retention_days' => 30,
            'reseal_interval_days' => 365,
        ]);

        $result = $this->service->applyPolicy($document, $policy);

        $this->assertEquals($policy->id, $result['retention_policy_id']);
        $this->assertNotNull($result['retention_expires_at']);
        $this->assertNotNull($result['next_reseal_at']);

        // Verify dates are calculated correctly
        $expectedExpiry = now()->addYears(7)->addDays(30);
        $this->assertEquals($expectedExpiry->toDateString(), $result['retention_expires_at']->toDateString());
    }

    public function test_get_retention_stats_returns_correct_counts(): void
    {
        $tenant = Tenant::factory()->create();

        // Create various archived documents
        for ($i = 0; $i < 3; $i++) {
            $doc = Document::factory()->for($tenant)->create();
            ArchivedDocument::factory()->create([
                'tenant_id' => $tenant->id,
                'document_id' => $doc->id,
                'retention_expires_at' => now()->addYears(5),
                'archive_status' => ArchivedDocument::STATUS_ACTIVE,
            ]);
        }

        // Create expired
        $expiredDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $expiredDoc->id,
            'retention_expires_at' => now()->subDay(),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        // Create expiring soon
        $expiringDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $expiringDoc->id,
            'retention_expires_at' => now()->addDays(15),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        $stats = $this->service->getRetentionStats($tenant->id);

        $this->assertEquals(5, $stats['total_archived']);
        $this->assertEquals(1, $stats['expired']);
        $this->assertEquals(1, $stats['expiring_30_days']);
    }

    public function test_create_policy_creates_valid_policy(): void
    {
        $tenant = Tenant::factory()->create();

        $data = [
            'tenant_id' => $tenant->id,
            'name' => 'Test Policy',
            'description' => 'A test retention policy',
            'retention_years' => 10,
            'on_expiry_action' => RetentionPolicy::ACTION_ARCHIVE,
        ];

        $policy = $this->service->createPolicy($data);

        $this->assertInstanceOf(RetentionPolicy::class, $policy);
        $this->assertEquals('Test Policy', $policy->name);
        $this->assertEquals(10, $policy->retention_years);
        $this->assertEquals($tenant->id, $policy->tenant_id);
    }

    public function test_validate_policy_detects_invalid_retention(): void
    {
        $errors = $this->service->validatePolicy([
            'retention_years' => 0,
        ]);

        $this->assertArrayHasKey('retention_years', $errors);
    }

    public function test_validate_policy_accepts_valid_retention(): void
    {
        $errors = $this->service->validatePolicy([
            'retention_years' => 5,
            'on_expiry_action' => 'notify',
        ]);

        $this->assertEmpty($errors);
    }
}
