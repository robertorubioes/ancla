<?php

namespace App\Livewire\Admin;

use App\Mail\TenantSuspendedMail;
use App\Mail\TenantWelcomeMail;
use App\Models\RetentionPolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class TenantManagement extends Component
{
    use WithPagination;

    // Filters
    public $search = '';

    public $statusFilter = '';

    public $planFilter = '';

    // Check if we should open create modal on mount
    public function mount()
    {
        if (request()->query('action') === 'create') {
            $this->openCreateModal();
        }
    }

    // Form fields
    public $showModal = false;

    public $editMode = false;

    public $tenantId = null;

    // Tenant fields
    public $name = '';

    public $slug = '';

    public $subdomain = '';

    public $contactEmail = '';

    public $plan = 'starter';

    public $status = 'trial';

    public $maxUsers = null;

    public $maxDocumentsPerMonth = null;

    public $trialEndsAt = null;

    public $adminNotes = '';

    // Admin user fields
    public $adminName = '';

    public $adminEmail = '';

    public $autoGeneratePassword = true;

    // Suspension
    public $showSuspendModal = false;

    public $suspendTenantId = null;

    public $suspensionReason = '';

    protected $queryString = ['search', 'statusFilter', 'planFilter'];

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|min:3|max:100',
            'slug' => 'required|string|min:3|max:50|unique:tenants,slug,'.$this->tenantId,
            'subdomain' => 'required|string|min:3|max:50|unique:tenants,subdomain,'.$this->tenantId,
            'contactEmail' => 'required|email',
            'plan' => 'required|in:free,starter,professional,enterprise',
            'status' => 'required|in:trial,active,suspended,cancelled',
            'maxUsers' => 'nullable|integer|min:1',
            'maxDocumentsPerMonth' => 'nullable|integer|min:1',
            'trialEndsAt' => 'nullable|date',
            'adminNotes' => 'nullable|string',
        ];

        // Add admin user validation only when creating (not editing)
        if (! $this->editMode) {
            $rules['adminName'] = 'required|string|min:2|max:255';
            $rules['adminEmail'] = 'required|email|unique:users,email';
        }

        return $rules;
    }

    public function updatedName($value)
    {
        if (! $this->editMode && empty($this->slug)) {
            $this->slug = $this->generateSlug($value);
            $this->subdomain = $this->slug;
        }
    }

    public function updatedSlug($value)
    {
        $this->slug = $this->generateSlug($value);
        if (! $this->editMode && empty($this->subdomain)) {
            $this->subdomain = $this->slug;
        }
    }

    public function updatedPlan($value)
    {
        $limits = Tenant::getPlanLimits($value);
        $this->maxUsers = $limits['max_users'];
        $this->maxDocumentsPerMonth = $limits['max_documents_per_month'];
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;

        // Set default trial end date (30 days)
        $this->trialEndsAt = now()->addDays(30)->format('Y-m-d');
    }

    public function openEditModal($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        $this->tenantId = $tenant->id;
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->subdomain = $tenant->subdomain;
        $this->contactEmail = $tenant->users()->where('role', 'admin')->first()?->email ?? '';
        $this->plan = $tenant->plan;
        $this->status = $tenant->status;
        $this->maxUsers = $tenant->max_users;
        $this->maxDocumentsPerMonth = $tenant->max_documents_per_month;
        $this->trialEndsAt = $tenant->trial_ends_at?->format('Y-m-d');
        $this->adminNotes = $tenant->admin_notes;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function saveTenant()
    {
        Log::info('========== SAVETENANT INICIO ==========');
        Log::info('saveTenant called', [
            'name' => $this->name,
            'adminEmail' => $this->adminEmail,
            'slug' => $this->slug,
            'subdomain' => $this->subdomain,
            'editMode' => $this->editMode
        ]);
        
        try {
            $this->validate();
            Log::info('Validación pasó correctamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validación falló', ['errors' => $e->errors()]);
            throw $e;
        };

        DB::beginTransaction();
        try {
            if ($this->editMode) {
                $tenant = Tenant::findOrFail($this->tenantId);
                $this->updateTenant($tenant);
            } else {
                $this->createTenant();
            }

            DB::commit();
            $this->closeModal();
            session()->flash('message', $this->editMode ? 'Tenant updated successfully.' : 'Tenant created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tenant creation/update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Error: '.$e->getMessage());

            // Re-throw in testing environment to make failures visible
            if (app()->environment('testing')) {
                throw $e;
            }
        }
    }

    protected function createTenant()
    {
        // Create tenant
        $tenant = Tenant::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'subdomain' => $this->subdomain,
            'plan' => $this->plan,
            'status' => $this->status,
            'max_users' => $this->maxUsers,
            'max_documents_per_month' => $this->maxDocumentsPerMonth,
            'trial_ends_at' => $this->trialEndsAt,
            'admin_notes' => $this->adminNotes,
            'settings' => [
                'branding' => [
                    'logo' => null,
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#1E40AF',
                ],
                'timezone' => 'Europe/Madrid',
                'locale' => 'en',
                'email_settings' => [
                    'from_name' => $this->name,
                ],
            ],
        ]);

        // Create admin user
        $temporaryPassword = Str::random(12);
        $adminUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => $this->adminName,
            'email' => $this->adminEmail,
            'password' => Hash::make($temporaryPassword),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create default retention policy for tenant
        RetentionPolicy::create([
            'uuid' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'name' => 'Default Policy',
            'description' => 'Default retention policy for '.$tenant->name,
            'retention_years' => 5,
            'priority' => 1,
            'is_active' => true,
        ]);

        // Send welcome email
        Mail::to($adminUser->email)->queue(new TenantWelcomeMail($tenant, $adminUser, $temporaryPassword));

        // Log tenant creation
        Log::info('Tenant created by superadmin', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'admin_email' => $adminUser->email,
            'plan' => $tenant->plan,
            'created_by' => auth()->user()->name,
        ]);
    }

    protected function updateTenant($tenant)
    {
        $oldPlan = $tenant->plan;
        $oldStatus = $tenant->status;

        $tenant->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'subdomain' => $this->subdomain,
            'plan' => $this->plan,
            'status' => $this->status,
            'max_users' => $this->maxUsers,
            'max_documents_per_month' => $this->maxDocumentsPerMonth,
            'trial_ends_at' => $this->trialEndsAt,
            'admin_notes' => $this->adminNotes,
        ]);

        // Log tenant update
        Log::info('Tenant updated by superadmin', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'changes' => [
                'plan' => ['from' => $oldPlan, 'to' => $this->plan],
                'status' => ['from' => $oldStatus, 'to' => $this->status],
            ],
            'updated_by' => auth()->user()->name,
        ]);
    }

    public function openSuspendModal($tenantId)
    {
        $this->suspendTenantId = $tenantId;
        $this->suspensionReason = '';
        $this->showSuspendModal = true;
    }

    public function suspendTenant()
    {
        $this->validate([
            'suspensionReason' => 'required|string|min:10|max:500',
        ]);

        $tenant = Tenant::findOrFail($this->suspendTenantId);

        if ($tenant->isSuspended()) {
            session()->flash('error', 'Tenant is already suspended.');
            $this->showSuspendModal = false;

            return;
        }

        $tenant->suspend($this->suspensionReason);

        // Send suspension email to admin users
        $adminUsers = $tenant->users()->where('role', 'admin')->get();
        foreach ($adminUsers as $admin) {
            Mail::to($admin->email)->queue(new TenantSuspendedMail($tenant, $this->suspensionReason));
        }

        // Log tenant suspension
        Log::warning('Tenant suspended by superadmin', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'reason' => $this->suspensionReason,
            'suspended_by' => auth()->user()->name,
        ]);

        session()->flash('message', 'Tenant suspended successfully.');
        $this->showSuspendModal = false;
    }

    public function unsuspendTenant($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->isSuspended()) {
            session()->flash('error', 'Tenant is not suspended.');

            return;
        }

        $tenant->unsuspend();

        // Log tenant unsuspension
        Log::info('Tenant unsuspended by superadmin', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'unsuspended_by' => auth()->user()->name,
        ]);

        session()->flash('message', 'Tenant unsuspended successfully.');
    }

    protected function generateSlug($value)
    {
        return Str::slug($value);
    }

    protected function resetForm()
    {
        $this->reset([
            'tenantId',
            'name',
            'slug',
            'subdomain',
            'contactEmail',
            'plan',
            'status',
            'maxUsers',
            'maxDocumentsPerMonth',
            'trialEndsAt',
            'adminNotes',
            'adminName',
            'adminEmail',
        ]);

        $this->plan = 'starter';
        $this->status = 'trial';
        $this->autoGeneratePassword = true;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedPlanFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Tenant::query()
            ->withCount('users')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('slug', 'like', '%'.$this->search.'%')
                    ->orWhere('subdomain', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->planFilter) {
            $query->where('plan', $this->planFilter);
        }

        $tenants = $query->paginate(10);

        // Calculate statistics
        $stats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'trial' => Tenant::where('status', 'trial')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
        ];

        return view('livewire.admin.tenant-management', [
            'tenants' => $tenants,
            'stats' => $stats,
        ]);
    }
}
