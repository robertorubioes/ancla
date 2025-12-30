<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TenantManagement;
use App\Mail\TenantSuspendedMail;
use App\Mail\TenantWelcomeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Tenant $superadminTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create superadmin tenant
        $this->superadminTenant = Tenant::create([
            'name' => 'Firmalum Admin',
            'slug' => 'ancla-admin',
            'subdomain' => 'admin',
            'plan' => 'enterprise',
            'status' => 'active',
        ]);

        // Create superadmin user
        $this->superadmin = User::create([
            'tenant_id' => $this->superadminTenant->id,
            'name' => 'Super Admin',
            'email' => 'superadmin@ancla.app',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function superadmin_can_access_tenant_management_page()
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('admin.tenants'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(TenantManagement::class);
    }

    /** @test */
    public function non_superadmin_cannot_access_tenant_management_page()
    {
        $regularTenant = Tenant::factory()->create();
        $regularUser = User::factory()->create([
            'tenant_id' => $regularTenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($regularUser)
            ->get(route('admin.tenants'));

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_tenant_management_page()
    {
        $response = $this->get(route('admin.tenants'));

        $response->assertStatus(302); // Redirects to login
    }

    /** @test */
    public function can_display_tenant_statistics()
    {
        Tenant::factory()->count(3)->create(['status' => 'active']);
        Tenant::factory()->count(2)->create(['status' => 'trial']);
        Tenant::factory()->count(1)->create(['status' => 'suspended']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->assertSee('Total Tenants')
            ->assertSee('7') // 6 created + 1 superadmin tenant
            ->assertSee('Active')
            ->assertSee('Trial')
            ->assertSee('Suspended');
    }

    /** @test */
    public function can_search_tenants_by_name()
    {
        $tenant1 = Tenant::factory()->create(['name' => 'Acme Corporation']);
        $tenant2 = Tenant::factory()->create(['name' => 'Tech Solutions']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->set('search', 'Acme')
            ->assertSee('Acme Corporation')
            ->assertDontSee('Tech Solutions');
    }

    /** @test */
    public function can_filter_tenants_by_status()
    {
        $activeTenant = Tenant::factory()->create(['status' => 'active', 'name' => 'Active Corp']);
        $trialTenant = Tenant::factory()->create(['status' => 'trial', 'name' => 'Trial Corp']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->set('statusFilter', 'active')
            ->assertSee('Active Corp')
            ->assertDontSee('Trial Corp');
    }

    /** @test */
    public function can_filter_tenants_by_plan()
    {
        $starterTenant = Tenant::factory()->create(['plan' => 'starter', 'name' => 'Starter Corp']);
        $professionalTenant = Tenant::factory()->create(['plan' => 'professional', 'name' => 'Professional Corp']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->set('planFilter', 'starter')
            ->assertSee('Starter Corp')
            ->assertDontSee('Professional Corp');
    }

    /** @test */
    public function can_create_new_tenant_with_admin_user()
    {
        Mail::fake();

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->set('name', 'New Organization')
            ->set('slug', 'new-org')
            ->set('subdomain', 'new-org')
            ->set('contactEmail', 'contact@neworg.com')
            ->set('plan', 'starter')
            ->set('status', 'trial')
            ->set('adminName', 'John Doe')
            ->set('adminEmail', 'john@neworg.com')
            ->call('saveTenant')
            ->assertHasNoErrors();

        // Assert tenant created
        $this->assertDatabaseHas('tenants', [
            'name' => 'New Organization',
            'slug' => 'new-org',
            'subdomain' => 'new-org',
            'plan' => 'starter',
            'status' => 'trial',
        ]);

        // Assert admin user created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@neworg.com',
            'role' => 'admin',
        ]);

        // Assert retention policy created
        $tenant = Tenant::where('slug', 'new-org')->first();
        $this->assertDatabaseHas('retention_policies', [
            'tenant_id' => $tenant->id,
            'name' => 'Default Policy',
        ]);

        // Assert welcome email sent
        Mail::assertQueued(TenantWelcomeMail::class, function ($mail) {
            return $mail->adminUser->email === 'john@neworg.com';
        });
    }

    /** @test */
    public function auto_generates_slug_from_name()
    {
        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->set('name', 'Test Company Inc')
            ->assertSet('slug', 'test-company-inc')
            ->assertSet('subdomain', 'test-company-inc');
    }

    /** @test */
    public function auto_applies_plan_limits_when_plan_selected()
    {
        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->set('plan', 'starter')
            ->assertSet('maxUsers', 5)
            ->assertSet('maxDocumentsPerMonth', 50);
    }

    /** @test */
    public function validates_required_fields_on_create()
    {
        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->call('saveTenant')
            ->assertHasErrors(['name', 'slug', 'subdomain', 'adminName', 'adminEmail']);
    }

    /** @test */
    public function validates_unique_slug()
    {
        $existingTenant = Tenant::factory()->create(['slug' => 'existing']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->set('name', 'New Tenant')
            ->set('slug', 'existing')
            ->set('subdomain', 'new-subdomain')
            ->set('contactEmail', 'test@test.com')
            ->set('adminName', 'Admin')
            ->set('adminEmail', 'admin@test.com')
            ->call('saveTenant')
            ->assertHasErrors(['slug']);
    }

    /** @test */
    public function validates_unique_subdomain()
    {
        $existingTenant = Tenant::factory()->create(['subdomain' => 'existing']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->set('name', 'New Tenant')
            ->set('slug', 'new-slug')
            ->set('subdomain', 'existing')
            ->set('contactEmail', 'test@test.com')
            ->set('adminName', 'Admin')
            ->set('adminEmail', 'admin@test.com')
            ->call('saveTenant')
            ->assertHasErrors(['subdomain']);
    }

    /** @test */
    public function can_edit_existing_tenant()
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original',
            'subdomain' => 'original',
        ]);

        // Create an admin user for the tenant
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'email' => 'admin@original.com',
        ]);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openEditModal', $tenant->id)
            ->assertSet('name', 'Original Name')
            ->set('name', 'Updated Name')
            ->set('plan', 'professional')
            ->set('contactEmail', 'admin@original.com')
            ->call('saveTenant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Name',
            'plan' => 'professional',
        ]);
    }

    /** @test */
    public function can_suspend_tenant_with_reason()
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['status' => 'active']);
        $adminUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openSuspendModal', $tenant->id)
            ->set('suspensionReason', 'Payment overdue for 30 days')
            ->call('suspendTenant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'suspended',
            'suspended_reason' => 'Payment overdue for 30 days',
        ]);

        $tenant->refresh();
        $this->assertNotNull($tenant->suspended_at);

        // Assert suspension email sent to admin
        Mail::assertQueued(TenantSuspendedMail::class, function ($mail) use ($adminUser) {
            return $mail->hasTo($adminUser->email);
        });
    }

    /** @test */
    public function validates_suspension_reason_min_length()
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openSuspendModal', $tenant->id)
            ->set('suspensionReason', 'Short')
            ->call('suspendTenant')
            ->assertHasErrors(['suspensionReason']);
    }

    /** @test */
    public function can_unsuspend_tenant()
    {
        $tenant = Tenant::factory()->create([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_reason' => 'Test reason',
        ]);

        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('unsuspendTenant', $tenant->id);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'active',
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);
    }

    /** @test */
    public function tenant_can_check_if_can_add_user()
    {
        $tenant = Tenant::factory()->create(['max_users' => 2]);

        // Can add when under limit
        $this->assertTrue($tenant->canAddUser());

        // Cannot add when at limit
        User::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $this->assertFalse($tenant->canAddUser());
    }

    /** @test */
    public function tenant_with_null_max_users_can_add_unlimited()
    {
        $tenant = Tenant::factory()->create(['max_users' => null]);

        User::factory()->count(100)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($tenant->canAddUser());
    }

    /** @test */
    public function tenant_can_check_document_quota()
    {
        $tenant = Tenant::factory()->create(['max_documents_per_month' => 10]);

        // Has not reached quota
        $this->assertFalse($tenant->hasReachedDocumentQuota());

        // Will need to create documents to fully test this
        // This is a basic validation that the method exists and runs
        $this->assertIsInt($tenant->getDocumentQuota());
        $this->assertEquals(10, $tenant->getDocumentQuota());
    }

    /** @test */
    public function tenant_suspension_changes_status_correctly()
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $this->assertFalse($tenant->isSuspended());

        $tenant->suspend('Test suspension');

        $this->assertTrue($tenant->isSuspended());
        $this->assertEquals('suspended', $tenant->status);
        $this->assertEquals('Test suspension', $tenant->suspended_reason);
    }

    /** @test */
    public function tenant_unsuspension_clears_suspension_fields()
    {
        $tenant = Tenant::factory()->create([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_reason' => 'Test reason',
        ]);

        $tenant->unsuspend();

        $this->assertFalse($tenant->isSuspended());
        $this->assertEquals('active', $tenant->status);
        $this->assertNull($tenant->suspended_at);
        $this->assertNull($tenant->suspended_reason);
    }

    /** @test */
    public function get_plan_limits_returns_correct_values()
    {
        $freeLimits = Tenant::getPlanLimits('free');
        $this->assertEquals(1, $freeLimits['max_users']);
        $this->assertEquals(10, $freeLimits['max_documents_per_month']);

        $starterLimits = Tenant::getPlanLimits('starter');
        $this->assertEquals(5, $starterLimits['max_users']);
        $this->assertEquals(50, $starterLimits['max_documents_per_month']);

        $enterpriseLimits = Tenant::getPlanLimits('enterprise');
        $this->assertNull($enterpriseLimits['max_users']);
        $this->assertNull($enterpriseLimits['max_documents_per_month']);
    }

    /** @test */
    public function tenant_can_apply_plan_limits()
    {
        $tenant = Tenant::factory()->create(['plan' => 'starter']);

        $tenant->applyPlanLimits();

        $this->assertEquals(5, $tenant->max_users);
        $this->assertEquals(50, $tenant->max_documents_per_month);
    }

    /** @test */
    public function closing_modal_resets_form()
    {
        Livewire::actingAs($this->superadmin)
            ->test(TenantManagement::class)
            ->call('openCreateModal')
            ->set('name', 'Test Name')
            ->set('slug', 'test-slug')
            ->call('closeModal')
            ->assertSet('name', '')
            ->assertSet('slug', '');
    }
}
