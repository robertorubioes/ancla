<?php

namespace Tests\Unit\Evidence;

use App\Models\AuditTrailEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\ChainVerificationResult;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditTrailServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditTrailService $auditTrailService;

    private HashingService $hashingService;

    private TsaService $tsaService;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('tenant', $this->tenant);

        // Enable TSA mock mode
        Config::set('evidence.tsa.mock', true);
        Config::set('evidence.audit.tsa_required_events', ['document.uploaded', 'document.signed']);

        $this->hashingService = new HashingService;
        $this->tsaService = new TsaService;
        $this->auditTrailService = new AuditTrailService($this->hashingService, $this->tsaService);
    }

    #[Test]
    public function it_records_audit_trail_entries(): void
    {
        $entry = $this->auditTrailService->record(
            $this->user,
            'user.created',
            ['action' => 'test']
        );

        $this->assertInstanceOf(AuditTrailEntry::class, $entry);
        $this->assertEquals('user.created', $entry->event_type);
        $this->assertEquals(['action' => 'test'], $entry->payload);
    }

    #[Test]
    public function it_generates_unique_uuids(): void
    {
        $entry1 = $this->auditTrailService->record($this->user, 'user.viewed', []);
        $entry2 = $this->auditTrailService->record($this->user, 'user.viewed', []);

        $this->assertNotEquals($entry1->uuid, $entry2->uuid);
    }

    #[Test]
    public function it_assigns_correct_tenant_id(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'user.viewed', []);

        $this->assertEquals($this->tenant->id, $entry->tenant_id);
    }

    #[Test]
    public function it_increments_sequence_numbers(): void
    {
        $entry1 = $this->auditTrailService->record($this->user, 'event.one', []);
        $entry2 = $this->auditTrailService->record($this->user, 'event.two', []);
        $entry3 = $this->auditTrailService->record($this->user, 'event.three', []);

        $this->assertEquals(1, $entry1->sequence);
        $this->assertEquals(2, $entry2->sequence);
        $this->assertEquals(3, $entry3->sequence);
    }

    #[Test]
    public function it_chains_hashes_correctly(): void
    {
        $entry1 = $this->auditTrailService->record($this->user, 'event.one', []);
        $entry2 = $this->auditTrailService->record($this->user, 'event.two', []);

        // First entry should have genesis hash as previous
        $this->assertEquals($this->auditTrailService->getGenesisHash(), $entry1->previous_hash);

        // Second entry should have first entry's hash as previous
        $this->assertEquals($entry1->hash, $entry2->previous_hash);
    }

    #[Test]
    public function it_calculates_entry_hashes(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'event.test', []);

        // Hash should be 64 characters (SHA-256)
        $this->assertEquals(64, strlen($entry->hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $entry->hash);
    }

    #[Test]
    public function it_sets_correct_event_category(): void
    {
        $docEntry = $this->auditTrailService->record($this->user, 'document.viewed', []);
        $signEntry = $this->auditTrailService->record($this->user, 'signature.created', []);
        $signerEntry = $this->auditTrailService->record($this->user, 'signer.invited', []);
        $accessEntry = $this->auditTrailService->record($this->user, 'access.granted', []);
        $systemEntry = $this->auditTrailService->record($this->user, 'system.updated', []);

        $this->assertEquals('document', $docEntry->event_category);
        $this->assertEquals('signature', $signEntry->event_category);
        $this->assertEquals('signature', $signerEntry->event_category);
        $this->assertEquals('access', $accessEntry->event_category);
        $this->assertEquals('system', $systemEntry->event_category);
    }

    #[Test]
    public function it_requests_tsa_for_critical_events(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'document.uploaded', []);

        $this->assertNotNull($entry->tsa_token_id);
    }

    #[Test]
    public function it_does_not_request_tsa_for_non_critical_events(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'document.viewed', []);

        $this->assertNull($entry->tsa_token_id);
    }

    #[Test]
    public function it_verifies_valid_chain(): void
    {
        // Create a chain of entries
        $this->auditTrailService->record($this->user, 'event.one', []);
        $this->auditTrailService->record($this->user, 'event.two', []);
        $this->auditTrailService->record($this->user, 'event.three', []);

        $result = $this->auditTrailService->verifyChain(
            User::class,
            $this->user->id
        );

        $this->assertInstanceOf(ChainVerificationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEquals(3, $result->entriesVerified);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function it_detects_empty_chain_as_valid(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->auditTrailService->verifyChain(
            User::class,
            $otherUser->id
        );

        $this->assertTrue($result->isValid());
        $this->assertEquals(0, $result->entriesVerified);
    }

    #[Test]
    public function it_gets_trail_for_model(): void
    {
        $this->auditTrailService->record($this->user, 'event.one', []);
        $this->auditTrailService->record($this->user, 'event.two', []);

        $trail = $this->auditTrailService->getTrailFor($this->user);

        $this->assertCount(2, $trail);
        $this->assertEquals('event.one', $trail[0]->event_type);
        $this->assertEquals('event.two', $trail[1]->event_type);
    }

    #[Test]
    public function it_gets_last_entry_for_model(): void
    {
        $this->auditTrailService->record($this->user, 'event.one', []);
        $this->auditTrailService->record($this->user, 'event.two', []);
        $this->auditTrailService->record($this->user, 'event.three', []);

        $lastEntry = $this->auditTrailService->getLastEntry($this->user);

        $this->assertEquals('event.three', $lastEntry->event_type);
        $this->assertEquals(3, $lastEntry->sequence);
    }

    #[Test]
    public function it_returns_null_for_model_without_entries(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $lastEntry = $this->auditTrailService->getLastEntry($otherUser);

        $this->assertNull($lastEntry);
    }

    #[Test]
    public function it_calculates_consistent_entry_hash(): void
    {
        $data = [
            'tenant_id' => 1,
            'auditable_type' => User::class,
            'auditable_id' => 1,
            'event_type' => 'test.event',
            'event_category' => 'system',
            'payload' => ['key' => 'value'],
            'actor_type' => 'user',
            'actor_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'sequence' => 1,
            'created_at' => '2025-01-01 00:00:00.000000',
        ];

        $previousHash = $this->auditTrailService->getGenesisHash();

        $hash1 = $this->auditTrailService->calculateEntryHash($data, $previousHash);
        $hash2 = $this->auditTrailService->calculateEntryHash($data, $previousHash);

        $this->assertEquals($hash1, $hash2);
    }

    #[Test]
    public function it_produces_different_hash_for_different_data(): void
    {
        $data1 = [
            'tenant_id' => 1,
            'auditable_type' => User::class,
            'auditable_id' => 1,
            'event_type' => 'event.one',
            'event_category' => 'system',
            'payload' => [],
            'actor_type' => 'user',
            'actor_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'sequence' => 1,
            'created_at' => '2025-01-01 00:00:00.000000',
        ];

        $data2 = array_merge($data1, ['event_type' => 'event.two']);

        $hash1 = $this->auditTrailService->calculateEntryHash($data1);
        $hash2 = $this->auditTrailService->calculateEntryHash($data2);

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function it_produces_different_hash_for_different_previous_hash(): void
    {
        $data = [
            'tenant_id' => 1,
            'auditable_type' => User::class,
            'auditable_id' => 1,
            'event_type' => 'test.event',
            'event_category' => 'system',
            'payload' => [],
            'actor_type' => 'user',
            'actor_id' => 1,
            'ip_address' => null,
            'user_agent' => null,
            'sequence' => 1,
            'created_at' => '2025-01-01 00:00:00.000000',
        ];

        $hash1 = $this->auditTrailService->calculateEntryHash($data, str_repeat('0', 64));
        $hash2 = $this->auditTrailService->calculateEntryHash($data, str_repeat('1', 64));

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function it_returns_genesis_hash(): void
    {
        $genesisHash = $this->auditTrailService->getGenesisHash();

        $this->assertEquals(64, strlen($genesisHash));
        $this->assertEquals(str_repeat('0', 64), $genesisHash);
    }

    #[Test]
    public function chain_verification_result_reports_errors(): void
    {
        $result = new ChainVerificationResult(
            valid: false,
            entriesVerified: 5,
            errors: ['Error 1', 'Error 2']
        );

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasErrors());
        $this->assertEquals(5, $result->getEntryCount());
        $this->assertCount(2, $result->errors);
    }

    #[Test]
    public function chain_verification_result_converts_to_array(): void
    {
        $result = new ChainVerificationResult(
            valid: true,
            entriesVerified: 10,
            errors: [],
            firstSequence: 1,
            lastSequence: 10
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('entries_verified', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('first_sequence', $array);
        $this->assertArrayHasKey('last_sequence', $array);
        $this->assertTrue($array['valid']);
        $this->assertEquals(10, $array['entries_verified']);
    }
}
