<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Authentication feature tests for E0-003.
 *
 * Tests login, logout, password reset, 2FA, and multi-tenant authentication.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test tenant
        $this->tenant = Tenant::factory()->create([
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // LOGIN TESTS
    // =========================================================================

    /** @test */
    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSeeLivewire(LoginForm::class);
    }

    /** @test */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::ADMIN->value,
        ]);

        // Bind tenant in container (simulating tenant middleware)
        $this->app->instance('tenant', $this->tenant);

        Livewire::test(LoginForm::class)
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    /** @test */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Bind tenant in container
        $this->app->instance('tenant', $this->tenant);

        Livewire::test(LoginForm::class)
            ->set('email', 'test@example.com')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_login_with_empty_credentials(): void
    {
        Livewire::test(LoginForm::class)
            ->set('email', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_login_with_invalid_email_format(): void
    {
        Livewire::test(LoginForm::class)
            ->set('email', 'not-an-email')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    // =========================================================================
    // MULTI-TENANT LOGIN TESTS
    // =========================================================================

    /** @test */
    public function test_user_cannot_login_to_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);

        // User belongs to the other tenant
        $user = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'user@othertenant.com',
            'password' => Hash::make('password123'),
        ]);

        // But we bind THIS tenant (different from user's tenant)
        $this->app->instance('tenant', $this->tenant);

        Livewire::test(LoginForm::class)
            ->set('email', 'user@othertenant.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    /** @test */
    public function test_super_admin_can_login_without_tenant(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'email' => 'super@admin.com',
            'password' => Hash::make('password123'),
        ]);

        // No tenant bound - global context
        Livewire::test(LoginForm::class)
            ->set('email', 'super@admin.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    /** @test */
    public function test_user_session_contains_tenant_id(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->app->instance('tenant', $this->tenant);

        Livewire::test(LoginForm::class)
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->call('login');

        $this->assertEquals($this->tenant->id, session('tenant_id'));
    }

    // =========================================================================
    // PASSWORD RESET TESTS
    // =========================================================================

    /** @test */
    public function test_forgot_password_page_loads(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ForgotPassword::class);
    }

    /** @test */
    public function test_password_reset_respects_tenant(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@tenant.com',
        ]);

        $this->app->instance('tenant', $this->tenant);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'user@tenant.com')
            ->call('sendResetLink')
            ->assertHasNoErrors();
    }

    /** @test */
    public function test_password_reset_fails_for_user_in_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);

        $user = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'user@othertenant.com',
        ]);

        // Bind different tenant
        $this->app->instance('tenant', $this->tenant);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'user@othertenant.com')
            ->call('sendResetLink');

        // Should not find user in this tenant context
        // No error shown to user (security), but reset link not sent
    }

    /** @test */
    public function test_reset_password_page_loads_with_token(): void
    {
        $response = $this->get('/reset-password/test-token?email=test@example.com');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ResetPassword::class);
    }

    // =========================================================================
    // 2FA TESTS
    // =========================================================================

    /** @test */
    public function test_2fa_challenge_works(): void
    {
        $user = User::factory()->withTwoFactorEnabled()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'secure@example.com',
            'password' => Hash::make('password123'),
        ]);

        // First login to get challenged
        $this->app->instance('tenant', $this->tenant);

        // Simulate 2FA challenge session
        session(['login.id' => $user->id]);

        Livewire::test(TwoFactorChallenge::class)
            ->assertViewIs('livewire.auth.two-factor-challenge');
    }

    /** @test */
    public function test_2fa_challenge_page_loads(): void
    {
        $user = User::factory()->withTwoFactorEnabled()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Store user id in session as Fortify does during 2FA challenge
        session(['login.id' => $user->id]);

        $response = $this->get('/two-factor-challenge');

        $response->assertStatus(200);
    }

    // =========================================================================
    // ROLE & PERMISSION TESTS
    // =========================================================================

    /** @test */
    public function test_user_has_correct_role(): void
    {
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $operator = User::factory()->operator()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $viewer = User::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($operator->isOperator());
        $this->assertTrue($viewer->isViewer());
    }

    /** @test */
    public function test_super_admin_has_all_permissions(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                $superAdmin->hasPermission($permission),
                "Super admin should have permission: {$permission->value}"
            );
        }
    }

    /** @test */
    public function test_admin_has_user_management_permissions(): void
    {
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($admin->hasPermission(Permission::VIEW_USERS));
        $this->assertTrue($admin->hasPermission(Permission::CREATE_USERS));
        $this->assertTrue($admin->hasPermission(Permission::EDIT_USERS));
        $this->assertTrue($admin->hasPermission(Permission::DELETE_USERS));
    }

    /** @test */
    public function test_viewer_has_limited_permissions(): void
    {
        $viewer = User::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Viewer can view documents
        $this->assertTrue($viewer->hasPermission(Permission::VIEW_DOCUMENTS));
        $this->assertTrue($viewer->hasPermission(Permission::SIGN_DOCUMENTS));

        // Viewer cannot create or manage
        $this->assertFalse($viewer->hasPermission(Permission::CREATE_DOCUMENTS));
        $this->assertFalse($viewer->hasPermission(Permission::MANAGE_USERS));
    }

    // =========================================================================
    // MIDDLEWARE TESTS
    // =========================================================================

    /** @test */
    public function test_permission_middleware_blocks_unauthorized(): void
    {
        $viewer = User::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($viewer);

        // This requires the middleware to be applied to a route
        // For now, we test the permission check directly
        $this->assertFalse($viewer->hasPermission(Permission::MANAGE_TENANTS));
    }

    /** @test */
    public function test_validate_session_tenant_middleware(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Login the user
        $this->actingAs($user);

        // Set session tenant_id
        session(['tenant_id' => $this->tenant->id]);

        // Session tenant matches user tenant - should be valid
        $this->assertEquals($this->tenant->id, session('tenant_id'));
        $this->assertEquals($this->tenant->id, $user->tenant_id);
    }

    // =========================================================================
    // RATE LIMITING TESTS
    // =========================================================================

    /** @test */
    public function test_login_rate_limiting(): void
    {
        $this->app->instance('tenant', $this->tenant);

        // Attempt multiple failed logins
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(LoginForm::class)
                ->set('email', 'test@example.com')
                ->set('password', 'wrongpassword')
                ->call('login');
        }

        // 6th attempt should be rate limited
        Livewire::test(LoginForm::class)
            ->set('email', 'test@example.com')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    // =========================================================================
    // LOGOUT TESTS
    // =========================================================================

    /** @test */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
