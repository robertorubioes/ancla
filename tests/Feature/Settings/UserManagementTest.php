<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Mail\UserWelcomeMail;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $operator;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Test Org']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
            'status' => 'active',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
            'status' => 'active',
        ]);

        $this->viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::VIEWER,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function admin_can_access_user_management_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.users'));

        $response->assertOk();
        $response->assertSeeLivewire('settings.user-management');
    }

    /** @test */
    public function operator_cannot_access_user_management_page(): void
    {
        $response = $this->actingAs($this->operator)->get(route('settings.users'));

        $response->assertForbidden();
    }

    /** @test */
    public function viewer_cannot_access_user_management_page(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('settings.users'));

        $response->assertForbidden();
    }

    /** @test */
    public function admin_can_see_all_tenant_users(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->assertSee($this->admin->name)
            ->assertSee($this->operator->name)
            ->assertSee($this->viewer->name);
    }

    /** @test */
    public function admin_can_invite_new_user(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'newuser@example.com')
            ->set('inviteName', 'New User')
            ->set('inviteRole', 'operator')
            ->set('inviteMessage', 'Welcome to our team!')
            ->call('inviteUser');

        $this->assertDatabaseHas('user_invitations', [
            'tenant_id' => $this->tenant->id,
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => 'operator',
            'invited_by' => $this->admin->id,
        ]);

        Mail::assertSent(UserInvitationMail::class, function ($mail) {
            return $mail->hasTo('newuser@example.com');
        });
    }

    /** @test */
    public function cannot_invite_user_with_existing_email(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', $this->operator->email)
            ->set('inviteName', 'Duplicate User')
            ->set('inviteRole', 'viewer')
            ->call('inviteUser')
            ->assertHasErrors(['inviteEmail']);

        $this->assertDatabaseMissing('user_invitations', [
            'email' => $this->operator->email,
        ]);
    }

    /** @test */
    public function cannot_invite_user_with_pending_invitation(): void
    {
        $existingInvitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'pending@example.com',
            name: 'Pending User',
            role: UserRole::VIEWER,
            invitedBy: $this->admin->id
        );

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'pending@example.com')
            ->set('inviteName', 'Another Name')
            ->set('inviteRole', 'operator')
            ->call('inviteUser')
            ->assertHasErrors(['inviteEmail']);
    }

    /** @test */
    public function invitation_email_validation(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'invalid-email')
            ->set('inviteName', 'Test User')
            ->set('inviteRole', 'viewer')
            ->call('inviteUser')
            ->assertHasErrors(['inviteEmail']);
    }

    /** @test */
    public function invitation_requires_name(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'test@example.com')
            ->set('inviteName', '')
            ->set('inviteRole', 'viewer')
            ->call('inviteUser')
            ->assertHasErrors(['inviteName']);
    }

    /** @test */
    public function invitation_requires_valid_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'test@example.com')
            ->set('inviteName', 'Test User')
            ->set('inviteRole', 'invalid_role')
            ->call('inviteUser')
            ->assertHasErrors(['inviteRole']);
    }

    /** @test */
    public function admin_can_resend_invitation(): void
    {
        Mail::fake();

        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'invited@example.com',
            name: 'Invited User',
            role: UserRole::OPERATOR,
            invitedBy: $this->admin->id
        );

        $originalToken = $invitation->token;

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('resendInvitation', $invitation->id);

        $invitation->refresh();

        $this->assertNotEquals($originalToken, $invitation->token);
        $this->assertEquals(1, $invitation->resend_count);

        Mail::assertSent(UserInvitationMail::class);
    }

    /** @test */
    public function cannot_resend_invitation_more_than_three_times(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'invited@example.com',
            name: 'Invited User',
            role: UserRole::OPERATOR,
            invitedBy: $this->admin->id
        );

        // Resend 3 times
        $invitation->resend();
        $invitation->resend();
        $invitation->resend();

        $this->assertFalse($invitation->canResend());
    }

    /** @test */
    public function admin_can_cancel_invitation(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'invited@example.com',
            name: 'Invited User',
            role: UserRole::OPERATOR,
            invitedBy: $this->admin->id
        );

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('cancelInvitation', $invitation->id);

        $this->assertDatabaseMissing('user_invitations', [
            'id' => $invitation->id,
        ]);
    }

    /** @test */
    public function admin_can_edit_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('editUser', $this->operator->id)
            ->set('editName', 'Updated Name')
            ->set('editEmail', 'updated@example.com')
            ->set('editRole', 'viewer')
            ->call('updateUser');

        $this->operator->refresh();

        $this->assertEquals('Updated Name', $this->operator->name);
        $this->assertEquals('updated@example.com', $this->operator->email);
        $this->assertEquals(UserRole::VIEWER, $this->operator->role);
    }

    /** @test */
    public function admin_cannot_change_own_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('editUser', $this->admin->id)
            ->set('editName', $this->admin->name)
            ->set('editEmail', $this->admin->email)
            ->set('editRole', 'viewer')
            ->call('updateUser')
            ->assertHasErrors(['editRole']);

        $this->admin->refresh();

        $this->assertEquals(UserRole::ADMIN, $this->admin->role);
    }

    /** @test */
    public function admin_can_deactivate_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('toggleUserStatus', $this->operator->id);

        $this->operator->refresh();

        $this->assertEquals('inactive', $this->operator->status);
    }

    /** @test */
    public function admin_can_reactivate_user(): void
    {
        $this->operator->update(['status' => 'inactive']);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('toggleUserStatus', $this->operator->id);

        $this->operator->refresh();

        $this->assertEquals('active', $this->operator->status);
    }

    /** @test */
    public function admin_cannot_deactivate_themselves(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('toggleUserStatus', $this->admin->id);

        $this->admin->refresh();

        $this->assertEquals('active', $this->admin->status);
    }

    /** @test */
    public function admin_can_delete_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('confirmDelete', $this->viewer->id)
            ->call('deleteUser');

        $this->assertSoftDeleted('users', [
            'id' => $this->viewer->id,
        ]);
    }

    /** @test */
    public function admin_cannot_delete_themselves(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('confirmDelete', $this->admin->id)
            ->call('deleteUser');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function cannot_delete_user_with_active_signing_processes(): void
    {
        SigningProcess::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->operator->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('confirmDelete', $this->operator->id)
            ->call('deleteUser');

        $this->assertDatabaseHas('users', [
            'id' => $this->operator->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function can_search_users_by_name(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('search', $this->operator->name)
            ->assertSee($this->operator->name);
    }

    /** @test */
    public function can_search_users_by_email(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('search', $this->operator->email)
            ->assertSee($this->operator->email);
    }

    /** @test */
    public function can_filter_users_by_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('roleFilter', 'operator')
            ->assertSee($this->operator->name)
            ->assertDontSee($this->viewer->name);
    }

    /** @test */
    public function can_filter_users_by_status(): void
    {
        $this->operator->update(['status' => 'inactive']);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('statusFilter', 'inactive')
            ->assertSee($this->operator->name);
    }

    /** @test */
    public function user_invitation_creates_secure_token(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'test@example.com',
            name: 'Test User',
            role: UserRole::VIEWER,
            invitedBy: $this->admin->id
        );

        $this->assertNotNull($invitation->token);
        $this->assertEquals(64, strlen($invitation->token));
    }

    /** @test */
    public function user_invitation_expires_after_seven_days(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'test@example.com',
            name: 'Test User',
            role: UserRole::VIEWER,
            invitedBy: $this->admin->id
        );

        $this->assertTrue($invitation->expires_at->isAfter(now()->addDays(6)));
        $this->assertTrue($invitation->expires_at->isBefore(now()->addDays(8)));
    }

    /** @test */
    public function guest_can_view_valid_invitation(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'test@example.com',
            name: 'Test User',
            role: UserRole::VIEWER,
            invitedBy: $this->admin->id
        );

        $response = $this->get(route('invitation.show', $invitation->token));

        $response->assertOk();
        $response->assertSee($invitation->email);
        $response->assertSee($this->tenant->name);
    }

    /** @test */
    public function guest_sees_error_for_invalid_invitation_token(): void
    {
        $response = $this->get(route('invitation.show', 'invalid-token'));

        $response->assertOk();
        $response->assertSee('Invalid Invitation');
    }

    /** @test */
    public function guest_sees_error_for_expired_invitation(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'test@example.com',
            name: 'Test User',
            role: UserRole::VIEWER,
            invitedBy: $this->admin->id
        );

        $invitation->update(['expires_at' => now()->subDay()]);

        $response = $this->get(route('invitation.show', $invitation->token));

        $response->assertOk();
        $response->assertSee('Invalid Invitation');
    }

    /** @test */
    public function guest_can_accept_invitation_and_create_account(): void
    {
        Mail::fake();

        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'newuser@example.com',
            name: 'New User',
            role: UserRole::OPERATOR,
            invitedBy: $this->admin->id
        );

        $response = $this->post(route('invitation.accept', $invitation->token), [
            'password' => 'SecureP@ssw0rd!',
            'password_confirmation' => 'SecureP@ssw0rd!',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'tenant_id' => $this->tenant->id,
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => 'operator',
            'status' => 'active',
        ]);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);

        Mail::assertSent(UserWelcomeMail::class, function ($mail) {
            return $mail->hasTo('newuser@example.com');
        });

        $this->assertAuthenticated();
    }

    /** @test */
    public function accepting_invitation_requires_valid_password(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'newuser@example.com',
            name: 'New User',
            role: UserRole::OPERATOR,
            invitedBy: $this->admin->id
        );

        $response = $this->post(route('invitation.accept', $invitation->token), [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    /** @test */
    public function accepting_invitation_requires_password_confirmation(): void
    {
        $invitation = UserInvitation::createInvitation(
            tenantId: $this->tenant->id,
            email: 'newuser@example.com',
            name: 'New User',
            role: UserRole::OPERATOR,
            invitedBy: $this->admin->id
        );

        $response = $this->post(route('invitation.accept', $invitation->token), [
            'password' => 'SecureP@ssw0rd!',
            'password_confirmation' => 'DifferentP@ssw0rd!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function admin_cannot_invite_super_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'newsuperadmin@example.com')
            ->set('inviteName', 'New Super Admin')
            ->set('inviteRole', 'super_admin')
            ->call('inviteUser')
            ->assertHasErrors(['inviteRole']);

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'newsuperadmin@example.com',
        ]);
    }

    /** @test */
    public function operator_cannot_invite_admin(): void
    {
        // Note: This test validates the logic, but in practice operators can't access user management
        // If an operator somehow bypassed the middleware, this validation would catch them
        Livewire::actingAs($this->operator)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->set('inviteEmail', 'newadmin@example.com')
            ->set('inviteName', 'New Admin')
            ->set('inviteRole', 'admin')
            ->call('inviteUser')
            ->assertHasErrors(['inviteRole']);

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'newadmin@example.com',
        ]);
    }

    /** @test */
    public function admin_cannot_assign_super_admin_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\UserManagement::class)
            ->call('editUser', $this->viewer->id)
            ->set('editName', $this->viewer->name)
            ->set('editEmail', $this->viewer->email)
            ->set('editRole', 'super_admin')
            ->call('updateUser')
            ->assertHasErrors(['editRole']);

        $this->viewer->refresh();

        $this->assertEquals(UserRole::VIEWER, $this->viewer->role);
    }
}
