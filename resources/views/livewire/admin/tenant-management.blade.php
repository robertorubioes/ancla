<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Tenant Management</h1>
            <p class="mt-2 text-sm text-gray-600">Manage organizations and their settings</p>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('message'))
            <div class="mb-4 rounded-lg bg-green-50 p-4 border border-green-200">
                <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 rounded-lg bg-red-50 p-4 border border-red-200">
                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Tenants</dt>
                                <dd class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-green-200 cursor-pointer hover:shadow-md transition" wire:click="$set('statusFilter', 'active')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Active</dt>
                                <dd class="text-2xl font-bold text-green-600">{{ $stats['active'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-yellow-200 cursor-pointer hover:shadow-md transition" wire:click="$set('statusFilter', 'trial')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Trial</dt>
                                <dd class="text-2xl font-bold text-yellow-600">{{ $stats['trial'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-red-200 cursor-pointer hover:shadow-md transition" wire:click="$set('statusFilter', 'suspended')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Suspended</dt>
                                <dd class="text-2xl font-bold text-red-600">{{ $stats['suspended'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
            <div class="p-6">
                <div class="flex flex-col space-y-4 sm:flex-row sm:space-y-0 sm:space-x-4 sm:items-end">
                    <!-- Search -->
                    <div class="flex-1">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="search" wire:model.live.debounce.300ms="search" placeholder="Search by name, slug, or subdomain..." class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                    </div>

                    <!-- Status Filter -->
                    <div class="sm:w-48">
                        <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="statusFilter" wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="">All Statuses</option>
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Plan Filter -->
                    <div class="sm:w-48">
                        <label for="planFilter" class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                        <select id="planFilter" wire:model.live="planFilter" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="">All Plans</option>
                            <option value="free">Free</option>
                            <option value="starter">Starter</option>
                            <option value="professional">Professional</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>

                    <!-- Create Button -->
                    <div class="sm:w-auto">
                        <button wire:click="openCreateModal" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 shadow-sm transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Create Tenant
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organization</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdomain</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($tenants as $tenant)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $tenant->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $tenant->slug }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-mono">{{ $tenant->subdomain }}.{{ config('app.base_domain') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($tenant->plan === 'enterprise') bg-purple-100 text-purple-800
                                        @elseif($tenant->plan === 'pro') bg-blue-100 text-blue-800
                                        @elseif($tenant->plan === 'basic') bg-green-100 text-green-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($tenant->plan) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                        @if($tenant->status === 'active') bg-green-100 text-green-800
                                        @elseif($tenant->status === 'trial') bg-yellow-100 text-yellow-800
                                        @elseif($tenant->status === 'suspended') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($tenant->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $tenant->users_count }} / {{ $tenant->max_users ?? '∞' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $tenant->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button wire:click="openEditModal({{ $tenant->id }})" class="text-blue-600 hover:text-blue-900 transition">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>

                                        @if($tenant->isSuspended())
                                            <button wire:click="unsuspendTenant({{ $tenant->id }})" class="text-green-600 hover:text-green-900 transition" title="Unsuspend">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </button>
                                        @else
                                            <button wire:click="openSuspendModal({{ $tenant->id }})" class="text-red-600 hover:text-red-900 transition" title="Suspend">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <p class="mt-4 text-sm text-gray-500">No tenants found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $tenants->links() }}
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 transition-opacity" style="background-color: rgba(0, 0, 0, 0.5);" wire:click="closeModal"></div>

                <!-- Modal content - z-index superior al overlay -->
                <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form wire:submit.prevent="saveTenant">
                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{ $editMode ? 'Edit Tenant' : 'Create New Tenant' }}
                                </h3>
                                <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <!-- Organization Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Organization Name *</label>
                                    <input type="text" id="name" wire:model.live="name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" required>
                                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <!-- Slug and Subdomain -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                                        <input type="text" id="slug" wire:model.blur="slug" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono text-sm" required>
                                        @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="subdomain" class="block text-sm font-medium text-gray-700 mb-1">Subdomain *</label>
                                        <div class="flex">
                                            <input type="text" id="subdomain" wire:model="subdomain" class="flex-1 rounded-l-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono text-sm" required>
                                            <span class="inline-flex items-center px-3 rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">.{{ config('app.base_domain') }}</span>
                                        </div>
                                        @error('subdomain') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                @if(!$editMode)
                                    <!-- Admin User Fields -->
                                    <div class="border-t border-gray-200 pt-4">
                                        <h4 class="text-sm font-medium text-gray-900 mb-3">Admin User</h4>
                                        
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="adminName" class="block text-sm font-medium text-gray-700 mb-1">Admin Name *</label>
                                                <input type="text" id="adminName" wire:model="adminName" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" required>
                                                @error('adminName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>

                                            <div>
                                                <label for="adminEmail" class="block text-sm font-medium text-gray-700 mb-1">Admin Email *</label>
                                                <input type="email" id="adminEmail" wire:model="adminEmail" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" required>
                                                @error('adminEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                        </div>

                                        <p class="mt-2 text-xs text-gray-500">A temporary password will be auto-generated and sent via email.</p>
                                    </div>
                                @endif

                                <!-- Plan and Status -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="plan" class="block text-sm font-medium text-gray-700 mb-1">Plan *</label>
                                        <select id="plan" wire:model.live="plan" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <option value="free">Free</option>
                                            <option value="starter">Starter</option>
                                            <option value="professional">Professional</option>
                                            <option value="enterprise">Enterprise</option>
                                        </select>
                                        @error('plan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                                        <select id="status" wire:model="status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <option value="trial">Trial</option>
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <!-- Limits -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="maxUsers" class="block text-sm font-medium text-gray-700 mb-1">Max Users</label>
                                        <input type="number" id="maxUsers" wire:model="maxUsers" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Unlimited">
                                        @error('maxUsers') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="maxDocumentsPerMonth" class="block text-sm font-medium text-gray-700 mb-1">Max Docs/Month</label>
                                        <input type="number" id="maxDocumentsPerMonth" wire:model="maxDocumentsPerMonth" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Unlimited">
                                        @error('maxDocumentsPerMonth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <!-- Trial End Date -->
                                <div>
                                    <label for="trialEndsAt" class="block text-sm font-medium text-gray-700 mb-1">Trial Ends At</label>
                                    <input type="date" id="trialEndsAt" wire:model="trialEndsAt" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    @error('trialEndsAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <!-- Admin Notes -->
                                <div>
                                    <label for="adminNotes" class="block text-sm font-medium text-gray-700 mb-1">Admin Notes</label>
                                    <textarea id="adminNotes" wire:model="adminNotes" rows="3" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Internal notes..."></textarea>
                                    @error('adminNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-3 flex items-center justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                Cancel
                            </button>
                            <button type="button" wire:click="saveTenant" class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                {{ $editMode ? 'Update Tenant' : 'Create Tenant' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Suspend Modal -->
    @if($showSuspendModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 transition-opacity" style="background-color: rgba(0, 0, 0, 0.5);" wire:click="$set('showSuspendModal', false)"></div>

                <!-- Modal content - z-index superior al overlay -->
                <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit.prevent="suspendTenant">
                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">Suspend Tenant</h3>
                                    <p class="text-sm text-gray-500 mt-1">This action will block all users from accessing the platform.</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="suspensionReason" class="block text-sm font-medium text-gray-700 mb-1">Suspension Reason *</label>
                                <textarea id="suspensionReason" wire:model="suspensionReason" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500" placeholder="Explain why this tenant is being suspended..." required></textarea>
                                @error('suspensionReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs text-gray-500">Minimum 10 characters required.</p>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-3 flex items-center justify-end space-x-3">
                            <button type="button" wire:click="$set('showSuspendModal', false)" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Suspend Tenant
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
