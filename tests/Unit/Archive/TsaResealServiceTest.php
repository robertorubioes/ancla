<?php

declare(strict_types=1);

namespace Tests\Unit\Archive;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\TsaChain;
use App\Models\TsaChainEntry;
use App\Models\TsaToken;
use App\Services\Archive\TsaResealService;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TsaResealServiceTest extends TestCase
{
    use RefreshDatabase;

    private TsaResealService $service;

    private MockInterface $hashingService;

    private MockInterface $tsaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hashingService = Mockery::mock(HashingService::class);
        $this->tsaService = Mockery::mock(TsaService::class);

        $this->service = new TsaResealService(
            $this->hashingService,
            $this->tsaService
        );
    }

    public function test_initialize_chain_creates_chain_and_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create([
            'content_hash' => hash('sha256', 'test content'),
        ]);

        $tsaToken = TsaToken::factory()->create([
            'tenant_id' => $tenant->id,
            'issued_at' => now(),
            'expires_at' => now()->addYears(2),
        ]);

        $this->tsaService
            ->shouldReceive('requestTimestamp')
            ->once()
            ->with($document->content_hash)
            ->andReturn($tsaToken);

        $chain = $this->service->initializeChain($document);

        $this->assertInstanceOf(TsaChain::class, $chain);
        $this->assertEquals($document->id, $chain->document_id);
        $this->assertEquals($tenant->id, $chain->tenant_id);
        $this->assertEquals(TsaChain::TYPE_DOCUMENT, $chain->chain_type);
        $this->assertEquals($document->content_hash, $chain->preserved_hash);
        $this->assertEquals(TsaChain::STATUS_ACTIVE, $chain->status);
        $this->assertEquals(1, $chain->seal_count);

        // Verify entry was created
        $entry = $chain->entries()->first();
        $this->assertNotNull($entry);
        $this->assertEquals(0, $entry->sequence_number);
        $this->assertEquals(TsaChainEntry::REASON_INITIAL, $entry->reseal_reason);
        $this->assertNull($entry->previous_entry_hash);
    }

    public function test_reseal_creates_new_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();
        $initialToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $initialToken->id,
            'seal_count' => 1,
            'status' => TsaChain::STATUS_ACTIVE,
        ]);

        $initialEntry = TsaChainEntry::factory()->create([
            'tsa_chain_id' => $chain->id,
            'tsa_token_id' => $initialToken->id,
            'sequence_number' => 0,
            'cumulative_hash' => hash('sha256', 'initial hash'),
        ]);

        $newToken = TsaToken::factory()->create([
            'tenant_id' => $tenant->id,
            'issued_at' => now(),
            'expires_at' => now()->addYears(2),
        ]);

        $this->hashingService
            ->shouldReceive('hashString')
            ->once()
            ->andReturn(hash('sha256', 'new cumulative hash'));

        $this->tsaService
            ->shouldReceive('requestTimestamp')
            ->once()
            ->andReturn($newToken);

        $newEntry = $this->service->reseal($chain);

        $this->assertInstanceOf(TsaChainEntry::class, $newEntry);
        $this->assertEquals(1, $newEntry->sequence_number);
        $this->assertEquals($initialEntry->id, $newEntry->previous_entry_id);
        $this->assertEquals($initialEntry->cumulative_hash, $newEntry->previous_entry_hash);
        $this->assertEquals(TsaChainEntry::REASON_SCHEDULED, $newEntry->reseal_reason);

        // Verify chain was updated
        $chain->refresh();
        $this->assertEquals(2, $chain->seal_count);
        $this->assertEquals(TsaChain::STATUS_ACTIVE, $chain->status);
    }

    public function test_verify_chain_returns_valid_for_correct_chain(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create([
            'content_hash' => hash('sha256', 'document content'),
        ]);
        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
            'preserved_hash' => $document->content_hash,
            'status' => TsaChain::STATUS_ACTIVE,
        ]);

        TsaChainEntry::factory()->create([
            'tsa_chain_id' => $chain->id,
            'tsa_token_id' => $tsaToken->id,
            'sequence_number' => 0,
            'previous_entry_hash' => null,
            'cumulative_hash' => $document->content_hash,
        ]);

        $this->tsaService
            ->shouldReceive('verifyTimestamp')
            ->once()
            ->andReturn(true);

        $result = $this->service->verifyChain($chain);

        $this->assertInstanceOf(ChainVerificationResult::class, $result);
        $this->assertTrue($result->isValid);
        $this->assertEquals(1, $result->entriesVerified);
        $this->assertEmpty($result->errors);
    }

    public function test_verify_chain_detects_sequence_gap(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();
        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
            'preserved_hash' => $document->content_hash,
        ]);

        // Create entries with a gap (0, 2 instead of 0, 1)
        TsaChainEntry::factory()->create([
            'tsa_chain_id' => $chain->id,
            'tsa_token_id' => $tsaToken->id,
            'sequence_number' => 0,
        ]);

        TsaChainEntry::factory()->create([
            'tsa_chain_id' => $chain->id,
            'tsa_token_id' => $tsaToken->id,
            'sequence_number' => 2, // Gap - missing sequence 1
        ]);

        $this->tsaService
            ->shouldReceive('verifyTimestamp')
            ->andReturn(true);

        $result = $this->service->verifyChain($chain);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Sequence gap', $result->errors[0]);
    }

    public function test_get_chains_due_for_reseal(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();
        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        // Chain due for reseal
        $dueChain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
            'status' => TsaChain::STATUS_ACTIVE,
            'next_seal_due_at' => now()->subDays(5),
        ]);

        // Chain not due
        TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
            'status' => TsaChain::STATUS_ACTIVE,
            'next_seal_due_at' => now()->addDays(30),
        ]);

        $dueChains = $this->service->getChainsDueForReseal(0);

        $this->assertCount(1, $dueChains);
        $this->assertEquals($dueChain->id, $dueChains->first()->id);
    }

    public function test_calculate_cumulative_hash(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->for($tenant)->create();
        $tsaToken = TsaToken::factory()->create(['tenant_id' => $tenant->id]);

        $chain = TsaChain::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'initial_tsa_token_id' => $tsaToken->id,
            'preserved_hash' => hash('sha256', 'preserved'),
        ]);

        TsaChainEntry::factory()->create([
            'tsa_chain_id' => $chain->id,
            'tsa_token_id' => $tsaToken->id,
            'sequence_number' => 0,
            'sealed_hash' => hash('sha256', 'sealed0'),
            'timestamp_value' => now(),
        ]);

        $this->hashingService
            ->shouldReceive('hashString')
            ->once()
            ->andReturn(hash('sha256', 'cumulative'));

        $hash = $this->service->calculateCumulativeHash($chain);

        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
