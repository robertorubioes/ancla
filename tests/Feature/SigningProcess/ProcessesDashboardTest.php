<?php

declare(strict_types=1);

namespace Tests\Feature\SigningProcess;

use App\Livewire\SigningProcess\ProcessesDashboard;
use App\Models\Document;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcessesDashboardTest extends TestCase
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
            'status' => 'ready',
        ]);
    }

    /** @test */
    public function it_renders_successfully_for_authenticated_user(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertStatus(200)
            ->assertSee('Signing Processes')
            ->assertSee('New Process');
    }

    /** @test */
    public function it_displays_statistics_correctly(): void
    {
        // Create processes with different statuses
        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);

        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertSee('3') // Total
            ->assertSee('1') // Draft
            ->assertSee('In Progress')
            ->assertSee('Completed');
    }

    /** @test */
    public function it_displays_processes_in_table(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_SENT,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertSee($this->document->original_filename)
            ->assertSee('Sent')
            ->assertSee('View Details');
    }

    /** @test */
    public function it_filters_by_status(): void
    {
        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        $completedProcess = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->call('setFilter', 'completed')
            ->assertSet('filterStatus', 'completed')
            ->assertSee($this->document->original_filename);
    }

    /** @test */
    public function it_searches_by_document_name(): void
    {
        $searchableDoc = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'original_filename' => 'contract-search-test.pdf',
            'status' => 'ready',
        ]);

        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $searchableDoc->id,
        ]);

        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->set('search', 'contract-search')
            ->assertSee('contract-search-test.pdf');
    }

    /** @test */
    public function it_searches_by_signer_name(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'name' => 'John Unique Doe',
            'email' => 'john@example.com',
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->set('search', 'Unique')
            ->assertSee($this->document->original_filename);
    }

    /** @test */
    public function it_clears_filters(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->set('filterStatus', 'completed')
            ->set('search', 'test')
            ->call('clearFilters')
            ->assertSet('filterStatus', null)
            ->assertSet('search', '');
    }

    /** @test */
    public function it_opens_details_modal(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->call('showDetails', $process->id)
            ->assertSet('selectedProcessId', $process->id)
            ->assertSet('showDetailsModal', true);
    }

    /** @test */
    public function it_closes_details_modal(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->call('showDetails', $process->id)
            ->call('closeDetails')
            ->assertSet('selectedProcessId', null)
            ->assertSet('showDetailsModal', false);
    }

    /** @test */
    public function it_displays_process_completion_percentage(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        // Create 2 signers, 1 signed
        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SIGNED,
        ]);

        Signer::factory()->create([
            'signing_process_id' => $process->id,
            'status' => Signer::STATUS_SENT,
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertSee('50%'); // 1 out of 2 = 50%
    }

    /** @test */
    public function it_displays_signer_timeline_in_details(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        $signer = Signer::factory()->create([
            'signing_process_id' => $process->id,
            'name' => 'Jane Timeline Doe',
            'email' => 'jane@example.com',
            'status' => Signer::STATUS_SIGNED,
            'sent_at' => now()->subDays(2),
            'viewed_at' => now()->subDays(1),
            'signed_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->call('showDetails', $process->id)
            ->assertSee('Jane Timeline Doe')
            ->assertSee('jane@example.com')
            ->assertSee('Signers Timeline');
    }

    /** @test */
    public function it_only_shows_processes_for_current_user(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $otherUser->id,
            'document_id' => $this->document->id,
        ]);

        $myProcess = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class);

        // Should see my process
        $this->assertEquals(1, $component->get('processes')->total());
    }

    /** @test */
    public function it_enforces_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => 'ready',
        ]);

        SigningProcess::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
            'document_id' => $otherDocument->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class);

        // Should not see processes from other tenant
        $this->assertEquals(0, $component->get('processes')->total());
    }

    /** @test */
    public function it_displays_empty_state_when_no_processes(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertSee('No processes found')
            ->assertSee('Create Your First Process');
    }

    /** @test */
    public function it_displays_deadline_information(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'deadline_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertSee($process->deadline_at->format('M d, Y'));
    }

    /** @test */
    public function it_displays_custom_message_in_details(): void
    {
        $process = SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'custom_message' => 'Please sign this important document.',
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->call('showDetails', $process->id)
            ->assertSee('Please sign this important document.');
    }

    /** @test */
    public function it_resets_pagination_when_filter_changes(): void
    {
        // Create 15 processes to force pagination
        for ($i = 0; $i < 15; $i++) {
            SigningProcess::factory()->create([
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id,
                'document_id' => $this->document->id,
                'status' => $i < 5 ? SigningProcess::STATUS_DRAFT : SigningProcess::STATUS_COMPLETED,
            ]);
        }

        $component = Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class);

        // Navigate to page 2
        $component->call('gotoPage', 2, 'page');

        // Apply filter should reset to page 1
        $component->call('setFilter', 'completed');

        // Verify we're on first page by checking processes
        $this->assertCount(10, $component->get('processes')->items());
    }

    /** @test */
    public function it_displays_signature_order_in_table(): void
    {
        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'signature_order' => 'sequential',
        ]);

        Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class)
            ->assertSee('Sequential');
    }

    /** @test */
    public function it_calculates_statistics_correctly(): void
    {
        SigningProcess::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_DRAFT,
        ]);

        SigningProcess::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'document_id' => $this->document->id,
            'status' => SigningProcess::STATUS_COMPLETED,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ProcessesDashboard::class);

        $stats = $component->get('statistics');

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(2, $stats['draft']);
        $this->assertEquals(3, $stats['completed']);
    }
}
