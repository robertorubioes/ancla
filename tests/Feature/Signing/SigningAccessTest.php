<?php

declare(strict_types=1);

namespace Tests\Feature\Signing;

use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SigningAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_signing_page_renders_successfully_with_valid_token(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 1,
        ]);

        $response = $this->get(route('sign.show', ['token' => $signer->token]));

        $response->assertOk();
        $response->assertSeeLivewire('signing.signing-page');
    }

    public function test_signing_page_returns_404_for_invalid_token(): void
    {
        $response = $this->get(route('sign.show', ['token' => 'invalid-token-12345678901234567']));

        $response->assertNotFound();
    }

    public function test_livewire_component_loads_signing_data(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'custom_message' => 'Please sign this important document.',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSet('token', $signer->token)
            ->assertSet('isLoading', false)
            ->assertSee('Jane Doe')
            ->assertSee('jane@example.com')
            ->assertSee('Please sign this important document');
    }

    public function test_first_access_records_audit_trail(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'viewed_at' => null,
        ]);

        $this->assertDatabaseMissing('audit_trail_entries', [
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
            'event_type' => 'signer.accessed',
        ]);

        $this->get(route('sign.show', ['token' => $signer->token]));

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
            'event_type' => 'signer.accessed',
        ]);
    }

    public function test_updates_signer_status_to_viewed_on_first_access(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'viewed_at' => null,
        ]);

        $this->assertSame(Signer::STATUS_SENT, $signer->status);
        $this->assertNull($signer->viewed_at);

        $this->get(route('sign.show', ['token' => $signer->token]));

        $signer->refresh();
        $this->assertSame(Signer::STATUS_VIEWED, $signer->status);
        $this->assertNotNull($signer->viewed_at);
    }

    public function test_updates_process_status_to_in_progress(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        $this->assertSame(SigningProcess::STATUS_SENT, $process->status);

        $this->get(route('sign.show', ['token' => $signer->token]));

        $process->refresh();
        $this->assertSame(SigningProcess::STATUS_IN_PROGRESS, $process->status);
    }

    public function test_shows_already_signed_message_for_signed_signer(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now()->subDay(),
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSet('alreadySigned', true)
            ->assertSet('canSign', false)
            ->assertSee('Document Signed');
    }

    public function test_shows_error_for_expired_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'deadline_at' => now()->subDay(),
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSet('hasError', true)
            ->assertSee('expired');
    }

    public function test_shows_error_for_cancelled_process(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_CANCELLED,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSet('hasError', true)
            ->assertSee('cancelled');
    }

    public function test_sequential_order_shows_waiting_message_when_not_turn(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 1,
            'name' => 'John Doe',
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer2->token])
            ->assertSet('canSign', false)
            ->assertSee('Please Wait')
            ->assertSee('John Doe');
    }

    public function test_sequential_order_can_sign_when_turn(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now()->subHour(),
            'order' => 1,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer2->token])
            ->assertSet('canSign', true)
            ->assertSee('Request Verification Code');
    }

    public function test_parallel_order_all_signers_can_sign(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer1 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 1,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer1->token])
            ->assertSet('canSign', true);

        Livewire::test('signing.signing-page', ['token' => $signer2->token])
            ->assertSet('canSign', true);
    }

    public function test_displays_custom_message_if_provided(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'custom_message' => 'This is a test custom message.',
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSee('This is a test custom message.');
    }

    public function test_displays_deadline_if_provided(): void
    {
        $deadline = now()->addDays(7);
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'deadline_at' => $deadline,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSee('Deadline')
            ->assertSee($deadline->format('d/m/Y'));
    }

    public function test_displays_signing_progress(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
            'signed_at' => now(),
            'order' => 1,
        ]);

        $signer2 = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
            'order' => 2,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer2->token])
            ->assertSee('Progress')
            ->assertSee('1 of 2 signed');
    }

    public function test_rate_limiting_applies_to_signing_route(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        // Make 11 requests (limit is 10 per minute)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->get(route('sign.show', ['token' => $signer->token]));
            $response->assertOk();
        }

        // The 11th request should be rate limited
        $response = $this->get(route('sign.show', ['token' => $signer->token]));
        $response->assertStatus(429);
    }

    public function test_displays_document_information(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSee('Document to Sign')
            ->assertSee($this->document->original_filename);
    }

    public function test_displays_promoter_information(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSee('Requested by')
            ->assertSee($this->user->name);
    }

    public function test_shows_sequential_badge_for_sequential_order(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSee('Sequential');
    }

    public function test_shows_parallel_badge_for_parallel_order(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_id' => $this->document->id,
            'created_by' => $this->user->id,
            'status' => SigningProcess::STATUS_SENT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::test('signing.signing-page', ['token' => $signer->token])
            ->assertSee('Parallel');
    }

    public function test_multi_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $process = SigningProcess::factory()->create([
            'tenant_id' => $otherTenant->id,
            'document_id' => $otherDocument->id,
            'created_by' => $otherUser->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        // Should still work - signing links are public regardless of tenant
        $response = $this->get(route('sign.show', ['token' => $signer->token]));
        $response->assertOk();
    }
}
