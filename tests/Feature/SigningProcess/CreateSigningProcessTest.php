<?php

declare(strict_types=1);

namespace Tests\Feature\SigningProcess;

use App\Livewire\SigningProcess\CreateSigningProcess;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateSigningProcessTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create a ready document
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => Document::STATUS_READY,
        ]);
    }

    /** @test */
    public function it_can_render_the_create_signing_process_component(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->assertOk()
            ->assertSee('Create Signing Process')
            ->assertSee('Select Document')
            ->assertSee('Add Signers')
            ->assertSee('Configure Process');
    }

    /** @test */
    public function it_initializes_with_one_empty_signer(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->assertSet('signers', [
                ['name' => '', 'email' => '', 'phone' => ''],
            ]);
    }

    /** @test */
    public function it_can_add_signers_up_to_maximum(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class);

        // Initially has 1 signer
        $component->assertCount('signers', 1);

        // Add signers up to 10
        for ($i = 1; $i < 10; $i++) {
            $component->call('addSigner')
                ->assertCount('signers', $i + 1);
        }

        // Try to add 11th signer (should fail)
        $component->call('addSigner')
            ->assertCount('signers', 10)
            ->assertSet('error', 'Maximum 10 signers allowed.');
    }

    /** @test */
    public function it_can_remove_signers_but_not_the_last_one(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class);

        // Add a second signer
        $component->call('addSigner')
            ->assertCount('signers', 2);

        // Remove second signer
        $component->call('removeSigner', 1)
            ->assertCount('signers', 1);

        // Try to remove last signer (should fail)
        $component->call('removeSigner', 0)
            ->assertCount('signers', 1)
            ->assertSet('error', 'At least one signer is required.');
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', null)
            ->set('signatureOrder', 'parallel')
            ->set('signers', [
                ['name' => '', 'email' => '', 'phone' => ''],
            ])
            ->call('create')
            ->assertHasErrors([
                'documentId',
                'signers.0.name',
                'signers.0.email',
            ]);
    }

    /** @test */
    public function it_validates_email_format(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'invalid-email', 'phone' => ''],
            ])
            ->call('create')
            ->assertHasErrors(['signers.0.email']);
    }

    /** @test */
    public function it_prevents_duplicate_emails(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
                ['name' => 'Jane Doe', 'email' => 'john@example.com', 'phone' => ''],
            ])
            ->call('create')
            ->assertHasErrors(['signers']);
    }

    /** @test */
    public function it_validates_deadline_must_be_in_future(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('deadlineAt', now()->subDay()->format('Y-m-d'))
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
            ])
            ->call('create')
            ->assertHasErrors(['deadlineAt']);
    }

    /** @test */
    public function it_can_create_signing_process_with_parallel_order(): void
    {
        $this->assertDatabaseCount('signing_processes', 0);
        $this->assertDatabaseCount('signers', 0);

        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signatureOrder', 'parallel')
            ->set('customMessage', 'Please sign this document')
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '+34600000001'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone' => '+34600000002'],
            ])
            ->call('create')
            ->assertSet('success', 'Signing process created successfully!')
            ->assertDispatched('process-created');

        $this->assertDatabaseCount('signing_processes', 1);
        $this->assertDatabaseCount('signers', 2);

        $process = SigningProcess::first();
        $this->assertEquals($this->document->id, $process->document_id);
        $this->assertEquals($this->tenant->id, $process->tenant_id);
        $this->assertEquals($this->user->id, $process->created_by);
        $this->assertEquals(SigningProcess::STATUS_DRAFT, $process->status);
        $this->assertEquals(SigningProcess::ORDER_PARALLEL, $process->signature_order);
        $this->assertEquals('Please sign this document', $process->custom_message);

        $signers = $process->signers()->orderBy('order')->get();
        $this->assertCount(2, $signers);

        $this->assertEquals('John Doe', $signers[0]->name);
        $this->assertEquals('john@example.com', $signers[0]->email);
        $this->assertEquals(0, $signers[0]->order);
        $this->assertEquals(Signer::STATUS_PENDING, $signers[0]->status);
        $this->assertNotEmpty($signers[0]->token);

        $this->assertEquals('Jane Smith', $signers[1]->name);
        $this->assertEquals('jane@example.com', $signers[1]->email);
        $this->assertEquals(1, $signers[1]->order);
    }

    /** @test */
    public function it_can_create_signing_process_with_sequential_order(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signatureOrder', 'sequential')
            ->set('signers', [
                ['name' => 'First Signer', 'email' => 'first@example.com', 'phone' => ''],
                ['name' => 'Second Signer', 'email' => 'second@example.com', 'phone' => ''],
            ])
            ->call('create');

        $process = SigningProcess::first();
        $this->assertEquals(SigningProcess::ORDER_SEQUENTIAL, $process->signature_order);
    }

    /** @test */
    public function it_can_create_signing_process_with_deadline(): void
    {
        $deadline = now()->addWeek()->format('Y-m-d');

        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('deadlineAt', $deadline)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
            ])
            ->call('create');

        $process = SigningProcess::first();
        $this->assertNotNull($process->deadline_at);
        $this->assertEquals($deadline, $process->deadline_at->format('Y-m-d'));
    }

    /** @test */
    public function it_normalizes_email_to_lowercase(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'JOHN@EXAMPLE.COM', 'phone' => ''],
            ])
            ->call('create');

        $signer = Signer::first();
        $this->assertEquals('john@example.com', $signer->email);
    }

    /** @test */
    public function it_generates_unique_tokens_for_each_signer(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone' => ''],
            ])
            ->call('create');

        $signers = Signer::all();
        $this->assertCount(2, $signers);
        $this->assertNotEquals($signers[0]->token, $signers[1]->token);
        $this->assertEquals(32, strlen($signers[0]->token));
        $this->assertEquals(32, strlen($signers[1]->token));
    }

    /** @test */
    public function it_only_shows_documents_from_same_tenant(): void
    {
        // Create another tenant with documents
        $otherTenant = Tenant::factory()->create();
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => Document::STATUS_READY,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class);

        $availableDocs = $component->get('availableDocuments');

        $this->assertTrue($availableDocs->contains('id', $this->document->id));
        $this->assertFalse($availableDocs->contains('id', $otherDocument->id));
    }

    /** @test */
    public function it_only_shows_ready_documents(): void
    {
        $pendingDoc = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => Document::STATUS_PENDING,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class);

        $availableDocs = $component->get('availableDocuments');

        $this->assertTrue($availableDocs->contains('id', $this->document->id));
        $this->assertFalse($availableDocs->contains('id', $pendingDoc->id));
    }

    /** @test */
    public function it_can_reset_the_form(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('customMessage', 'Test message')
            ->set('deadlineAt', now()->addWeek()->format('Y-m-d'))
            ->set('signatureOrder', 'sequential');

        $component->call('addSigner');
        $component->assertCount('signers', 2);

        $component->call('resetForm')
            ->assertSet('documentId', null)
            ->assertSet('customMessage', null)
            ->assertSet('deadlineAt', null)
            ->assertSet('signatureOrder', 'parallel')
            ->assertCount('signers', 1);
    }

    /** @test */
    public function it_validates_custom_message_length(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('customMessage', str_repeat('a', 501))
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
            ])
            ->call('create')
            ->assertHasErrors(['customMessage']);
    }

    /** @test */
    public function it_trims_whitespace_from_signer_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => '  John Doe  ', 'email' => '  JOHN@EXAMPLE.COM  ', 'phone' => '  +34600000000  '],
            ])
            ->call('create');

        $signer = Signer::first();
        $this->assertEquals('John Doe', $signer->name);
        $this->assertEquals('john@example.com', $signer->email);
        $this->assertEquals('+34600000000', $signer->phone);
    }

    /** @test */
    public function it_handles_empty_phone_numbers(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
            ])
            ->call('create');

        $signer = Signer::first();
        $this->assertNull($signer->phone);
    }

    /** @test */
    public function it_creates_audit_trail_entry(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSigningProcess::class)
            ->set('documentId', $this->document->id)
            ->set('signers', [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => ''],
            ])
            ->call('create');

        $this->assertDatabaseHas('audit_trail_entries', [
            'event_type' => 'signing_process.created',
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
