<?php

namespace Tests\Unit;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing tenant context
        if (app()->bound('tenant')) {
            app()->forgetInstance('tenant');
        }
    }

    // ==========================================
    // TenantScope Tests
    // ==========================================

    public function test_tenant_scope_filters_users_by_current_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create users for each tenant (without global scope filtering)
        $user1 = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant1->id,
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        $user2 = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant2->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        // Set tenant 1 as current context
        app()->instance('tenant', $tenant1);

        // Query should only return tenant 1's users
        $users = User::all();

        $this->assertCount(1, $users);
        $this->assertEquals($user1->id, $users->first()->id);
    }

    public function test_tenant_scope_returns_all_when_no_tenant_context(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant1->id,
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant2->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        // No tenant context
        $users = User::all();

        $this->assertCount(2, $users);
    }

    public function test_without_tenant_scope_returns_all_users(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant1->id,
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant2->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        // Set tenant context
        app()->instance('tenant', $tenant1);

        // Query without tenant scope should return all
        $users = User::withoutTenantScope()->get();

        $this->assertCount(2, $users);
    }

    // ==========================================
    // BelongsToTenant Trait Tests
    // ==========================================

    public function test_belongs_to_tenant_auto_assigns_tenant_id_on_create(): void
    {
        $tenant = Tenant::factory()->create();

        // Set tenant context
        app()->instance('tenant', $tenant);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        $this->assertEquals($tenant->id, $user->tenant_id);
    }

    public function test_user_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        $this->assertInstanceOf(Tenant::class, $user->tenant);
        $this->assertEquals($tenant->id, $user->tenant->id);
    }

    public function test_for_tenant_scope_filters_by_specific_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant1->id,
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant2->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        // Query for specific tenant
        $users = User::forTenant($tenant2)->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User 2', $users->first()->name);
    }

    // ==========================================
    // Tenant Model Tests
    // ==========================================

    public function test_tenant_generates_uuid_on_create(): void
    {
        $tenant = Tenant::factory()->create(['uuid' => null]);

        $this->assertNotNull($tenant->uuid);
        $this->assertTrue(strlen($tenant->uuid) === 36);
    }

    public function test_tenant_is_active_when_status_active(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $this->assertTrue($tenant->isActive());
    }

    public function test_tenant_is_active_when_on_valid_trial(): void
    {
        $tenant = Tenant::factory()->onTrial()->create();

        $this->assertTrue($tenant->isActive());
        $this->assertTrue($tenant->isOnTrial());
    }

    public function test_tenant_is_not_active_when_trial_expired(): void
    {
        $tenant = Tenant::factory()->expiredTrial()->create();

        $this->assertFalse($tenant->isActive());
        $this->assertTrue($tenant->hasTrialExpired());
    }

    public function test_tenant_is_suspended(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->assertTrue($tenant->isSuspended());
        $this->assertFalse($tenant->isActive());
    }

    public function test_tenant_has_users_relationship(): void
    {
        $tenant = Tenant::factory()->create();

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertCount(2, $tenant->users);
    }

    public function test_tenant_url_uses_domain_when_set(): void
    {
        $tenant = Tenant::factory()->withDomain('custom.example.com')->create();

        $this->assertStringContainsString('custom.example.com', $tenant->url);
    }

    public function test_tenant_url_uses_slug_when_no_domain(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'mycompany']);

        $this->assertStringContainsString('mycompany', $tenant->url);
    }

    public function test_tenant_settings_are_cast_to_array(): void
    {
        $settings = ['feature_a' => true, 'max_users' => 10];
        $tenant = Tenant::factory()->withSettings($settings)->create();

        $this->assertIsArray($tenant->settings);
        $this->assertEquals(true, $tenant->getSetting('feature_a'));
        $this->assertEquals(10, $tenant->getSetting('max_users'));
        $this->assertNull($tenant->getSetting('non_existent'));
        $this->assertEquals('default', $tenant->getSetting('non_existent', 'default'));
    }

    // ==========================================
    // TenantContext Service Tests
    // ==========================================

    public function test_tenant_context_set_and_get(): void
    {
        $tenant = Tenant::factory()->create();
        $context = new TenantContext;

        $context->set($tenant);

        $this->assertInstanceOf(Tenant::class, $context->get());
        $this->assertEquals($tenant->id, $context->get()->id);
    }

    public function test_tenant_context_check_returns_false_when_empty(): void
    {
        $context = new TenantContext;

        $this->assertFalse($context->check());
    }

    public function test_tenant_context_check_returns_true_when_set(): void
    {
        $tenant = Tenant::factory()->create();
        $context = new TenantContext;

        $context->set($tenant);

        $this->assertTrue($context->check());
    }

    public function test_tenant_context_clear(): void
    {
        $tenant = Tenant::factory()->create();
        $context = new TenantContext;

        $context->set($tenant);
        $this->assertTrue($context->check());

        $context->clear();
        $this->assertFalse($context->check());
    }

    public function test_tenant_context_run_executes_callback_in_tenant_context(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $context = new TenantContext;

        // Set initial context
        $context->set($tenant1);

        // Run callback in different tenant context
        $result = $context->run($tenant2, function ($tenant) use ($context) {
            return $context->id();
        });

        // Callback should have run with tenant2
        $this->assertEquals($tenant2->id, $result);

        // Original context should be restored
        $this->assertEquals($tenant1->id, $context->id());
    }

    public function test_tenant_context_run_without_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $context = new TenantContext;

        // Set initial context
        $context->set($tenant);

        // Run callback without tenant
        $result = $context->runWithoutTenant(function () use ($context) {
            return $context->check();
        });

        // Callback should have run without tenant
        $this->assertFalse($result);

        // Original context should be restored
        $this->assertEquals($tenant->id, $context->id());
    }

    public function test_tenant_context_get_or_fail_throws_when_empty(): void
    {
        $context = new TenantContext;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tenant context available.');

        $context->getOrFail();
    }

    // ==========================================
    // User Role Tests
    // ==========================================

    public function test_user_is_super_admin(): void
    {
        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin()); // Super admins are also admins
    }

    public function test_user_is_admin(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_user_is_operator(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'password',
            'role' => 'operator',
        ]);

        $this->assertTrue($user->isOperator());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_is_viewer(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        $this->assertTrue($user->isViewer());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isOperator());
    }

    public function test_user_has_role(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('viewer'));
    }

    public function test_user_has_any_role(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'password',
            'role' => 'operator',
        ]);

        $this->assertTrue($user->hasAnyRole(['admin', 'operator']));
        $this->assertFalse($user->hasAnyRole(['admin', 'viewer']));
    }
}
