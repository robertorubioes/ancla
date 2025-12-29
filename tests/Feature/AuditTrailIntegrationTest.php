<?php

namespace Tests\Feature;

use App\Models\AuditTrailEntry;
use App\Models\Tenant;
use App\Models\TsaToken;
use App\Models\User;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\HashingService;
use App\Services\Evidence\TsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditTrailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private AuditTrailService $auditTrailService;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable TSA mock mode
        Config::set('evidence.tsa.mock', true);
        Config::set('evidence.audit.tsa_required_events', [
            'document.uploaded',
            'document.signed',
        ]);

        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);

        // Create service
        $this->auditTrailService = new AuditTrailService(
            new HashingService,
            new TsaService
        );
    }

    #[Test]
    public function it_creates_complete_audit_trail_chain(): void
    {
        // Simulate document lifecycle
        $entry1 = $this->auditTrailService->record($this->user, 'document.uploaded', [
            'filename' => 'contract.pdf',
            'size' => 1024,
        ]);

        $entry2 = $this->auditTrailService->record($this->user, 'document.viewed', [
            'viewer' => 'john@example.com',
        ]);

        $entry3 = $this->auditTrailService->record($this->user, 'document.signed', [
            'signer' => 'jane@example.com',
            'signature_type' => 'advanced',
        ]);

        // Verify chain integrity
        $this->assertEquals($this->auditTrailService->getGenesisHash(), $entry1->previous_hash);
        $this->assertEquals($entry1->hash, $entry2->previous_hash);
        $this->assertEquals($entry2->hash, $entry3->previous_hash);

        // Verify sequence
        $this->assertEquals(1, $entry1->sequence);
        $this->assertEquals(2, $entry2->sequence);
        $this->assertEquals(3, $entry3->sequence);

        // Verify TSA tokens for critical events
        $this->assertNotNull($entry1->tsa_token_id); // document.uploaded is critical
        $this->assertNull($entry2->tsa_token_id);    // document.viewed is not critical
        $this->assertNotNull($entry3->tsa_token_id); // document.signed is critical
    }

    #[Test]
    public function it_maintains_chain_integrity_under_concurrent_writes(): void
    {
        // Simulate rapid sequential writes (simulating concurrent behavior)
        $entries = [];
        for ($i = 1; $i <= 10; $i++) {
            $entries[] = $this->auditTrailService->record(
                $this->user,
                "event.{$i}",
                ['iteration' => $i]
            );
        }

        // Verify complete chain
        $result = $this->auditTrailService->verifyChain(User::class, $this->user->id);

        $this->assertTrue($result->isValid());
        $this->assertEquals(10, $result->entriesVerified);

        // Verify sequence continuity
        for ($i = 0; $i < 10; $i++) {
            $this->assertEquals($i + 1, $entries[$i]->sequence);

            if ($i > 0) {
                $this->assertEquals($entries[$i - 1]->hash, $entries[$i]->previous_hash);
            }
        }
    }

    #[Test]
    public function it_isolates_audit_trails_by_tenant(): void
    {
        // Create second tenant
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // Record events for first tenant
        $this->auditTrailService->record($this->user, 'tenant1.event1', []);
        $this->auditTrailService->record($this->user, 'tenant1.event2', []);

        // Switch tenant context
        app()->instance('tenant', $tenant2);

        // Record events for second tenant
        $service2 = new AuditTrailService(new HashingService, new TsaService);
        $service2->record($user2, 'tenant2.event1', []);

        // Verify tenant 1 trail
        $tenant1Trail = AuditTrailEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->get();
        $this->assertCount(2, $tenant1Trail);

        // Verify tenant 2 trail
        $tenant2Trail = AuditTrailEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenant2->id)
            ->get();
        $this->assertCount(1, $tenant2Trail);
    }

    #[Test]
    public function it_detects_tampered_entries(): void
    {
        // Create valid chain
        $entry1 = $this->auditTrailService->record($this->user, 'event.one', []);
        $entry2 = $this->auditTrailService->record($this->user, 'event.two', []);
        $entry3 = $this->auditTrailService->record($this->user, 'event.three', []);

        // Verify chain is valid
        $result = $this->auditTrailService->verifyChain(User::class, $this->user->id);
        $this->assertTrue($result->isValid());

        // Tamper with an entry (directly update the database)
        AuditTrailEntry::withoutGlobalScopes()
            ->where('id', $entry2->id)
            ->update(['hash' => str_repeat('x', 64)]);

        // Verify chain detects tampering
        $result = $this->auditTrailService->verifyChain(User::class, $this->user->id);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors);
    }

    #[Test]
    public function it_creates_tsa_tokens_with_correct_hash(): void
    {
        // Record event that requires TSA
        $entry = $this->auditTrailService->record($this->user, 'document.signed', [
            'signer' => 'test@example.com',
        ]);

        // Load TSA token
        $tsaToken = TsaToken::find($entry->tsa_token_id);

        $this->assertNotNull($tsaToken);
        $this->assertEquals($entry->hash, $tsaToken->data_hash);
        $this->assertEquals(TsaToken::STATUS_VALID, $tsaToken->status);
    }

    #[Test]
    public function it_stores_complete_event_context(): void
    {
        // Authenticate user for actor tracking
        $this->actingAs($this->user);

        $entry = $this->auditTrailService->record($this->user, 'document.viewed', [
            'page' => 1,
        ]);

        $this->assertEquals($this->tenant->id, $entry->tenant_id);
        $this->assertEquals(User::class, $entry->auditable_type);
        $this->assertEquals($this->user->id, $entry->auditable_id);
        $this->assertEquals('document.viewed', $entry->event_type);
        $this->assertEquals('document', $entry->event_category);
        $this->assertEquals(['page' => 1], $entry->payload);
        $this->assertEquals('user', $entry->actor_type);
        $this->assertEquals($this->user->id, $entry->actor_id);
    }

    #[Test]
    public function it_retrieves_audit_trail_in_chronological_order(): void
    {
        $this->auditTrailService->record($this->user, 'event.first', []);
        usleep(1000); // Small delay to ensure different timestamps
        $this->auditTrailService->record($this->user, 'event.second', []);
        usleep(1000);
        $this->auditTrailService->record($this->user, 'event.third', []);

        $trail = $this->auditTrailService->getTrailFor($this->user);

        $this->assertEquals('event.first', $trail[0]->event_type);
        $this->assertEquals('event.second', $trail[1]->event_type);
        $this->assertEquals('event.third', $trail[2]->event_type);
    }

    #[Test]
    public function it_handles_null_payload(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'simple.event', []);

        $this->assertEquals([], $entry->payload);
    }

    #[Test]
    public function it_handles_complex_payload(): void
    {
        $payload = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'document' => [
                'id' => 123,
                'pages' => [1, 2, 3],
            ],
            'metadata' => [
                'source' => 'api',
                'version' => '1.0',
            ],
        ];

        $entry = $this->auditTrailService->record($this->user, 'complex.event', $payload);

        $this->assertEquals($payload, $entry->payload);
    }

    #[Test]
    public function it_verifies_chain_returns_sequence_range(): void
    {
        $this->auditTrailService->record($this->user, 'event.one', []);
        $this->auditTrailService->record($this->user, 'event.two', []);
        $this->auditTrailService->record($this->user, 'event.three', []);

        $result = $this->auditTrailService->verifyChain(User::class, $this->user->id);

        $this->assertEquals(1, $result->firstSequence);
        $this->assertEquals(3, $result->lastSequence);
    }

    #[Test]
    public function entry_model_can_verify_its_own_integrity(): void
    {
        $entry1 = $this->auditTrailService->record($this->user, 'event.one', []);
        $entry2 = $this->auditTrailService->record($this->user, 'event.two', []);

        // Each entry should verify against its predecessor
        $this->assertTrue($entry1->verifyIntegrity(null));
        $this->assertTrue($entry2->verifyIntegrity($entry1));
    }

    #[Test]
    public function tsa_tokens_can_be_verified(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'document.uploaded', []);
        $tsaToken = TsaToken::find($entry->tsa_token_id);

        $tsaService = new TsaService;
        $isValid = $tsaService->verifyTimestamp($tsaToken);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_persists_all_entries_to_database(): void
    {
        // Record multiple events
        for ($i = 0; $i < 5; $i++) {
            $this->auditTrailService->record($this->user, "event.{$i}", ['index' => $i]);
        }

        // Check database
        $count = AuditTrailEntry::count();
        $this->assertEquals(5, $count);
    }

    #[Test]
    public function it_creates_tsa_tokens_for_critical_events_only(): void
    {
        // Create various events
        $this->auditTrailService->record($this->user, 'document.uploaded', []);
        $this->auditTrailService->record($this->user, 'document.viewed', []);
        $this->auditTrailService->record($this->user, 'document.signed', []);
        $this->auditTrailService->record($this->user, 'document.downloaded', []);

        // Check TSA token count (only uploaded and signed are critical)
        $tsaCount = TsaToken::count();
        $this->assertEquals(2, $tsaCount);
    }

    #[Test]
    public function audit_entries_belong_to_correct_tenant(): void
    {
        $entry = $this->auditTrailService->record($this->user, 'test.event', []);

        $this->assertEquals($this->tenant->id, $entry->tenant_id);
        $this->assertEquals($this->tenant->id, $entry->tenant->id);
    }

    #[Test]
    public function it_handles_unicode_in_payload(): void
    {
        $payload = [
            'message' => 'Héllo Wörld! 你好 🎉',
            'author' => 'José García',
        ];

        $entry = $this->auditTrailService->record($this->user, 'unicode.event', $payload);

        $this->assertEquals($payload, $entry->payload);
    }
}
