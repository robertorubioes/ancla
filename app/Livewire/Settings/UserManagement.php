<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    // Search and filters
    public $search = '';

    public $roleFilter = '';

    public $statusFilter = '';

    // Invite user modal
    public $showInviteModal = false;

    public $inviteEmail = '';

    public $inviteName = '';

    public $inviteRole = 'viewer';

    public $inviteMessage = '';

    // Edit user modal
    public $showEditModal = false;

    public $editingUser = null;

    public $editName = '';

    public $editEmail = '';

    public $editRole = '';

    // Delete confirmation
    public $showDeleteModal = false;

    public $deletingUser = null;

    protected $queryString = ['search', 'roleFilter', 'statusFilter'];

    /**
     * Validation rules for invitation.
     */
    protected function inviteRules(): array
    {
        return [
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteRole' => ['required', 'in:admin,operator,viewer'],
            'inviteMessage' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Validation rules for editing user.
     */
    protected function editRules(): array
    {
        return [
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255'],
            'editRole' => ['required', 'in:admin,operator,viewer'],
        ];
    }

    /**
     * Open invite modal.
     */
    public function openInviteModal(): void
    {
        $this->reset(['inviteEmail', 'inviteName', 'inviteRole', 'inviteMessage']);
        $this->inviteRole = 'viewer';
        $this->showInviteModal = true;
    }

    /**
     * Close invite modal.
     */
    public function closeInviteModal(): void
    {
        $this->showInviteModal = false;
        $this->resetValidation();
    }

    /**
     * Send invitation.
     */
    public function inviteUser(): void
    {
        $this->validate($this->inviteRules());

        $tenant = auth()->user()->tenant;

        // Check if user already exists
        $existingUser = User::where('tenant_id', $tenant->id)
            ->where('email', $this->inviteEmail)
            ->first();

        if ($existingUser) {
            $this->addError('inviteEmail', 'A user with this email already exists in your organization.');

            return;
        }

        // Check if there's already a pending invitation
        $existingInvitation = UserInvitation::where('tenant_id', $tenant->id)
            ->where('email', $this->inviteEmail)
            ->pending()
            ->first();

        if ($existingInvitation) {
            $this->addError('inviteEmail', 'There is already a pending invitation for this email address.');

            return;
        }

        // SEC-014: Validate user can assign this role
        $role = UserRole::from($this->inviteRole);
        if (! auth()->user()->canAssignRole($role)) {
            $this->addError('inviteRole', 'You do not have permission to invite users with this role.');

            return;
        }

        // Create invitation
        $invitation = UserInvitation::createInvitation(
            tenantId: $tenant->id,
            email: $this->inviteEmail,
            name: $this->inviteName,
            role: UserRole::from($this->inviteRole),
            invitedBy: auth()->id(),
            message: $this->inviteMessage
        );

        // Send invitation email
        Mail::to($invitation->email)->send(
            new UserInvitationMail($invitation, $tenant, auth()->user())
        );

        session()->flash('message', "Invitation sent to {$this->inviteEmail}");

        $this->closeInviteModal();
    }

    /**
     * Resend invitation.
     */
    public function resendInvitation(int $invitationId): void
    {
        $invitation = UserInvitation::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($invitationId);

        if (! $invitation->canResend()) {
            session()->flash('error', 'This invitation cannot be resent. Maximum resend limit reached or already accepted.');

            return;
        }

        $invitation->resend();

        // Send email again
        Mail::to($invitation->email)->send(
            new UserInvitationMail($invitation, auth()->user()->tenant, auth()->user())
        );

        session()->flash('message', "Invitation resent to {$invitation->email}");
    }

    /**
     * Cancel invitation.
     */
    public function cancelInvitation(int $invitationId): void
    {
        $invitation = UserInvitation::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($invitationId);

        if ($invitation->isAccepted()) {
            session()->flash('error', 'Cannot cancel an accepted invitation.');

            return;
        }

        $invitation->delete();

        session()->flash('message', 'Invitation cancelled successfully');
    }

    /**
     * Open edit modal.
     */
    public function editUser(int $userId): void
    {
        $this->editingUser = User::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($userId);

        $this->editName = $this->editingUser->name;
        $this->editEmail = $this->editingUser->email;
        $this->editRole = $this->editingUser->role->value;

        $this->showEditModal = true;
    }

    /**
     * Close edit modal.
     */
    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingUser = null;
        $this->resetValidation();
    }

    /**
     * Update user.
     */
    public function updateUser(): void
    {
        $this->validate($this->editRules());

        // Prevent admin from changing own role
        if ($this->editingUser->id === auth()->id() && $this->editRole !== auth()->user()->role->value) {
            $this->addError('editRole', 'You cannot change your own role.');

            return;
        }

        // SEC-014: Validate user can assign this role
        $newRole = UserRole::from($this->editRole);
        if (! auth()->user()->canAssignRole($newRole)) {
            $this->addError('editRole', 'You do not have permission to assign this role.');

            return;
        }

        $oldRole = $this->editingUser->role->value;
        $oldEmail = $this->editingUser->email;

        $this->editingUser->update([
            'name' => $this->editName,
            'email' => $this->editEmail,
            'role' => $this->editRole,
        ]);

        session()->flash('message', 'User updated successfully');

        $this->closeEditModal();
    }

    /**
     * Toggle user active status.
     */
    public function toggleUserStatus(int $userId): void
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($userId);

        // Prevent admin from deactivating themselves
        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');

            return;
        }

        $newStatus = $user->isActive() ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        // Log event with Laravel's built-in logging (TODO: Integrate with AuditTrailService)
        \Log::info($newStatus === 'active' ? 'user.reactivated' : 'user.deactivated', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'changed_by' => auth()->id(),
            'changed_by_name' => auth()->user()->name,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        session()->flash('message', 'User '.($newStatus === 'active' ? 'activated' : 'deactivated').' successfully');
    }

    /**
     * Open delete confirmation modal.
     */
    public function confirmDelete(int $userId): void
    {
        $this->deletingUser = User::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($userId);

        $this->showDeleteModal = true;
    }

    /**
     * Close delete modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingUser = null;
    }

    /**
     * Delete user.
     */
    public function deleteUser(): void
    {
        if (! $this->deletingUser) {
            return;
        }

        // Prevent admin from deleting themselves
        if ($this->deletingUser->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            $this->closeDeleteModal();

            return;
        }

        // Check for active signing processes
        if ($this->deletingUser->hasActiveSigningProcesses()) {
            session()->flash('error', 'Cannot delete user with active signing processes. Please complete or cancel them first.');
            $this->closeDeleteModal();

            return;
        }

        $userEmail = $this->deletingUser->email;
        $this->deletingUser->delete();

        session()->flash('message', "User {$userEmail} deleted successfully");

        $this->closeDeleteModal();
    }

    /**
     * Reset search and filters.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->resetPage();
    }

    /**
     * Get users query.
     */
    protected function getUsersQuery()
    {
        $query = User::where('tenant_id', auth()->user()->tenant_id)
            ->with('tenant');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get invitations query.
     */
    protected function getInvitationsQuery()
    {
        return UserInvitation::where('tenant_id', auth()->user()->tenant_id)
            ->with(['invitedBy', 'tenant'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Render component.
     */
    public function render()
    {
        return view('livewire.settings.user-management', [
            'users' => $this->getUsersQuery()->paginate(10),
            'invitations' => $this->getInvitationsQuery()->get(),
            'availableRoles' => UserRole::tenantRoles(),
        ])->layout('components.layouts.app');
    }
}
