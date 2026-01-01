<?php

declare(strict_types=1);

namespace Tests\Unit\Archive;

use App\Models\ArchivedDocument;
use App\Models\Document;
use App\Models\RetentionPolicy;
use App\Models\Tenant;
use App\Models\TsaChain;
use App\Models\TsaToken;
use App\Services\Archive\LongTermArchiveService;
use App\Services\Archive\RetentionPolicyService;
use App\Services\Archive\TsaResealService;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\HashingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LongTermArchiveServiceTest extends TestCase
{
    use RefreshDatabase;

    private LongTermArchiveService $service;

    private MockInterface $hashingService;

    private MockInterface $resealService;

    private MockInterface $policyService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->hashingService = Mockery::mock(HashingService::class);
        $this->resealService = Mockery::mock(TsaResealService::class);
        $this->policyService = Mockery::mock(RetentionPolicyService::class);

        $this->service = new LongTermArchiveService(
            $this->hashingService,
            $this->resealService,
            $this->policyService
        );
    }

    public function test_archive_creates_archived_document(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create([
            'content_hash' => hash('sha256', 'test content'),
            'stored_path' => 'documents/test.pdf',
        ]);

        $policy = RetentionPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'retention_years' => 5,
        ]);

        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);
        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
        ]);

        // Create the source file
        Storage::disk('local')->put('documents/test.pdf', 'PDF content');

        $this->policyService
            ->shouldReceive('getPolicyForDocument')
            ->once()
            ->with($document)
            ->andReturn($policy);

        $this->policyService
            ->shouldReceive('applyPolicy')
            ->once()
            ->andReturn([
                'retention_policy_id' => $policy->id,
                'retention_expires_at' => now()->addYears(5),
                'next_reseal_at' => now()->addYear(),
                'require_pdfa_conversion' => false,
                'target_pdfa_version' => null,
            ]);

        $this->hashingService
            ->shouldReceive('hashString')
            ->once()
            ->andReturn(hash('sha256', 'archive hash'));

        $this->resealService
            ->shouldReceive('initializeChain')
            ->once()
            ->with($document)
            ->andReturn($chain);

        $archived = $this->service->archive($document);

        $this->assertInstanceOf(ArchivedDocument::class, $archived);
        $this->assertEquals($document->id, $archived->document_id);
        $this->assertEquals($tenant->id, $archived->tenant_id);
        $this->assertEquals(ArchivedDocument::TIER_HOT, $archived->archive_tier);
        $this->assertEquals(ArchivedDocument::STATUS_ACTIVE, $archived->archive_status);
        $this->assertEquals($document->content_hash, $archived->content_hash);
    }

    public function test_move_tier_updates_document_tier(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        Storage::disk('local')->put('archive/test.pdf', 'PDF content');

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'archive_tier' => ArchivedDocument::TIER_HOT,
            'original_storage_path' => 'archive/test.pdf',
            'archive_storage_path' => 'archive/test.pdf',
            'storage_disk' => 'local',
        ]);

        $updated = $this->service->moveTier($archived, ArchivedDocument::TIER_COLD);

        $this->assertEquals(ArchivedDocument::TIER_COLD, $updated->archive_tier);
        $this->assertEquals(ArchivedDocument::STATUS_ACTIVE, $updated->archive_status);
    }

    public function test_move_tier_throws_for_invalid_tier(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'archive_tier' => ArchivedDocument::TIER_HOT,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->moveTier($archived, 'invalid_tier');
    }

    public function test_verify_integrity_returns_valid_for_correct_document(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();
        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        $archiveHash = hash('sha256', 'archive content');

        Storage::disk('local')->put('archive/test.pdf', 'archive content');

        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
        ]);

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'archive_hash' => $archiveHash,
            'archive_storage_path' => 'archive/test.pdf',
            'storage_disk' => 'local',
            'current_tsa_chain_id' => $chain->id,
            'retention_expires_at' => now()->addYears(5),
            'next_reseal_at' => now()->addMonths(6),
        ]);

        $this->hashingService
            ->shouldReceive('hashDocument')
            ->once()
            ->andReturn($archiveHash);

        $verificationResult = new ChainVerificationResult(
            valid: true,
            entriesVerified: 1,
            errors: []
        );

        $this->resealService
            ->shouldReceive('verifyChain')
            ->once()
            ->andReturn($verificationResult);

        $result = $this->service->verifyIntegrity($archived);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_verify_integrity_detects_hash_mismatch(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();
        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        Storage::disk('local')->put('archive/test.pdf', 'modified content');

        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
        ]);

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'archive_hash' => hash('sha256', 'original content'),
            'archive_storage_path' => 'archive/test.pdf',
            'storage_disk' => 'local',
            'current_tsa_chain_id' => $chain->id,
            'retention_expires_at' => now()->addYears(5),
        ]);

        $this->hashingService
            ->shouldReceive('hashDocument')
            ->once()
            ->andReturn(hash('sha256', 'modified content')); // Different hash

        $verificationResult = new ChainVerificationResult(
            valid: true,
            entriesVerified: 1,
            errors: []
        );

        $this->resealService
            ->shouldReceive('verifyChain')
            ->once()
            ->andReturn($verificationResult);

        $result = $this->service->verifyIntegrity($archived);

        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('hash mismatch', $result['errors'][0]);
    }

    public function test_get_documents_due_for_reseal(): void
    {
        $tenant = Tenant::factory()->create();

        // Create document due for reseal
        $dueDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $dueDoc->id,
            'next_reseal_at' => now()->subDays(5),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        // Create document not due
        $notDueDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $notDueDoc->id,
            'next_reseal_at' => now()->addDays(30),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        $dueDocuments = $this->service->getDocumentsDueForReseal(0);

        $this->assertCount(1, $dueDocuments);
        $this->assertEquals($dueDoc->id, $dueDocuments->first()->document_id);
    }

    public function test_get_documents_for_tier_migration(): void
    {
        $tenant = Tenant::factory()->create();

        // Create document in hot tier for more than 1 year
        $hotDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $hotDoc->id,
            'archive_tier' => ArchivedDocument::TIER_HOT,
            'archived_at' => now()->subDays(400), // More than 365 days
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        // Create recent document in hot tier
        $recentDoc = Document::factory()->for($tenant)->create();
        ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $recentDoc->id,
            'archive_tier' => ArchivedDocument::TIER_HOT,
            'archived_at' => now()->subDays(100),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
        ]);

        $migrations = $this->service->getDocumentsForTierMigration();

        $this->assertCount(1, $migrations);
        $this->assertEquals($hotDoc->id, $migrations->first()['document']->document_id);
        $this->assertEquals(ArchivedDocument::TIER_COLD, $migrations->first()['target_tier']);
    }

    public function test_get_statistics_returns_correct_counts(): void
    {
        $tenant = Tenant::factory()->create();

        // Create documents in different tiers
        foreach ([ArchivedDocument::TIER_HOT, ArchivedDocument::TIER_HOT, ArchivedDocument::TIER_COLD] as $tier) {
            $doc = Document::factory()->for($tenant)->create();
            ArchivedDocument::factory()->create([
                'tenant_id' => $tenant->id,
                'document_id' => $doc->id,
                'archive_tier' => $tier,
                'archive_status' => ArchivedDocument::STATUS_ACTIVE,
                'retention_expires_at' => now()->addYears(5),
            ]);
        }

        $this->policyService
            ->shouldReceive('getRetentionStats')
            ->once()
            ->andReturn([
                'total_archived' => 3,
                'expired' => 0,
                'expiring_30_days' => 0,
                'expiring_90_days' => 0,
                'healthy' => 3,
                'percentage_healthy' => 100,
            ]);

        $stats = $this->service->getStatistics($tenant->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['by_tier']['hot']);
        $this->assertEquals(1, $stats['by_tier']['cold']);
        $this->assertEquals(0, $stats['by_tier']['archive']);
    }

    public function test_restore_updates_last_accessed(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();

        $archived = ArchivedDocument::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'archive_tier' => ArchivedDocument::TIER_HOT,
            'last_accessed_at' => null,
        ]);

        $this->assertNull($archived->last_accessed_at);

        $result = $this->service->restore($archived);

        $archived->refresh();
        $this->assertNotNull($archived->last_accessed_at);
        $this->assertEquals($document->id, $result->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
