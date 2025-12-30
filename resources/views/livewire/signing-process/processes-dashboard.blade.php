<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Signing Processes</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage and monitor all your signing processes</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <a href="{{ route('signing-processes.create') }}" 
                       class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white text-sm font-medium rounded-lg shadow-lg transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Process
                    </a>
                </div>
            </div>
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {{-- Total --}}
            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 p-6 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Processes</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $this->statistics['total'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- In Progress --}}
            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 p-6 border border-gray-200 cursor-pointer"
                 wire:click="setFilter('in_progress')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">In Progress</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-2">{{ $this->statistics['in_progress'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Completed --}}
            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 p-6 border border-gray-200 cursor-pointer"
                 wire:click="setFilter('completed')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-3xl font-bold text-green-600 mt-2">{{ $this->statistics['completed'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Draft --}}
            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 p-6 border border-gray-200 cursor-pointer"
                 wire:click="setFilter('draft')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Drafts</p>
                        <p class="text-3xl font-bold text-gray-600 mt-2">{{ $this->statistics['draft'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-gradient-to-br from-gray-400 to-gray-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                {{-- Search --}}
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Search by document name, signer...">
                    </div>
                </div>

                {{-- Status Filter --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-medium text-gray-700">Status:</span>
                    <button wire:click="setFilter(null)" 
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors {{ $filterStatus === null ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        All
                    </button>
                    <button wire:click="setFilter('sent')" 
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors {{ $filterStatus === 'sent' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Sent
                    </button>
                    <button wire:click="setFilter('in_progress')" 
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors {{ $filterStatus === 'in_progress' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        In Progress
                    </button>
                    <button wire:click="setFilter('completed')" 
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors {{ $filterStatus === 'completed' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Completed
                    </button>
                </div>
            </div>

            @if($filterStatus || $search)
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-sm text-gray-600">Active filters:</span>
                    @if($filterStatus)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                            Status: {{ $this->getStatusLabel($filterStatus) }}
                            <button wire:click="setFilter(null)" class="ml-1 hover:text-purple-900">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </span>
                    @endif
                    @if($search)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                            Search: "{{ $search }}"
                            <button wire:click="$set('search', '')" class="ml-1 hover:text-purple-900">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </span>
                    @endif
                    <button wire:click="clearFilters" class="text-xs text-purple-600 hover:text-purple-800 font-medium">
                        Clear all
                    </button>
                </div>
            @endif
        </div>

        {{-- Processes Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @if($this->processes->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->processes as $process)
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-purple-500 to-blue-500 rounded-lg flex items-center justify-center">
                                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ Str::limit($process->document->original_filename, 40) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $process->signature_order === 'sequential' ? 'Sequential' : 'Parallel' }} signing
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $color = $this->getStatusColor($process->status);
                                            $colorClasses = [
                                                'gray' => 'bg-gray-100 text-gray-800',
                                                'blue' => 'bg-blue-100 text-blue-800',
                                                'yellow' => 'bg-yellow-100 text-yellow-800',
                                                'green' => 'bg-green-100 text-green-800',
                                                'red' => 'bg-red-100 text-red-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClasses[$color] ?? $colorClasses['gray'] }}">
                                            {{ $this->getStatusLabel($process->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-gradient-to-r from-purple-600 to-blue-600 h-2 rounded-full transition-all duration-300" 
                                                     style="width: {{ $process->getCompletionPercentage() }}%"></div>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900">{{ $process->getCompletionPercentage() }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center text-sm text-gray-900">
                                            <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <span class="font-medium">{{ $process->getCompletedSignersCount() }}</span>
                                            <span class="text-gray-500">/{{ $process->getTotalSignersCount() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $process->created_at->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if($process->deadline_at)
                                            <div class="flex items-center {{ $process->hasExpired() ? 'text-red-600' : '' }}">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                {{ $process->deadline_at->format('M d, Y') }}
                                            </div>
                                        @else
                                            <span class="text-gray-400">No deadline</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="showDetails({{ $process->id }})"
                                                    class="text-purple-600 hover:text-purple-900 font-medium transition-colors">
                                                View Details
                                            </button>
                                            
                                            @if($process->isCompleted() && $process->hasFinalDocument())
                                                <div class="relative inline-block text-left" x-data="{ open: false }">
                                                    <button @click="open = !open"
                                                            class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                        </svg>
                                                        Download
                                                    </button>
                                                    
                                                    <div x-show="open"
                                                         @click.away="open = false"
                                                         x-transition:enter="transition ease-out duration-100"
                                                         x-transition:enter-start="transform opacity-0 scale-95"
                                                         x-transition:enter-end="transform opacity-100 scale-100"
                                                         x-transition:leave="transition ease-in duration-75"
                                                         x-transition:leave-start="transform opacity-100 scale-100"
                                                         x-transition:leave-end="transform opacity-0 scale-95"
                                                         class="origin-top-right absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10"
                                                         style="display: none;">
                                                        <div class="py-1">
                                                            <a href="{{ route('signing-processes.download-document', $process) }}"
                                                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                                </svg>
                                                                Signed Document
                                                            </a>
                                                            <a href="{{ route('signing-processes.download-dossier', $process) }}"
                                                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                                </svg>
                                                                Evidence Dossier
                                                            </a>
                                                            <a href="{{ route('signing-processes.download-bundle', $process) }}"
                                                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-t border-gray-100">
                                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                                                </svg>
                                                                Complete Bundle (ZIP)
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $this->processes->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No processes found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($filterStatus || $search)
                            Try adjusting your filters or search query.
                        @else
                            Get started by creating a new signing process.
                        @endif
                    </p>
                    @if(!$filterStatus && !$search)
                        <div class="mt-6">
                            <a href="{{ route('signing-processes.create') }}" 
                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white text-sm font-medium rounded-lg shadow-lg transition duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create Your First Process
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Details Modal --}}
        @if($showDetailsModal && $this->selectedProcess)
            <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showDetailsModal') }">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    {{-- Background overlay --}}
                    <div x-show="show" 
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" 
                         wire:click="closeDetails"></div>

                    {{-- Modal panel --}}
                    <div x-show="show"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
                        
                        {{-- Modal Header --}}
                        <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-blue-600">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-bold text-white">Process Details</h3>
                                    <p class="text-sm text-purple-100 mt-1">{{ $this->selectedProcess->document->original_filename }}</p>
                                </div>
                                <button wire:click="closeDetails" class="text-white hover:text-gray-200 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Modal Body --}}
                        <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
                            
                            {{-- Process Info --}}
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase">Status</div>
                                    @php
                                        $color = $this->getStatusColor($this->selectedProcess->status);
                                        $colorClasses = [
                                            'gray' => 'bg-gray-100 text-gray-800',
                                            'blue' => 'bg-blue-100 text-blue-800',
                                            'yellow' => 'bg-yellow-100 text-yellow-800',
                                            'green' => 'bg-green-100 text-green-800',
                                            'red' => 'bg-red-100 text-red-800',
                                        ];
                                    @endphp
                                    <div class="mt-2">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $colorClasses[$color] ?? $colorClasses['gray'] }}">
                                            {{ $this->getStatusLabel($this->selectedProcess->status) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase">Signature Order</div>
                                    <div class="mt-2 text-sm font-medium text-gray-900">
                                        {{ ucfirst($this->selectedProcess->signature_order) }}
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase">Created</div>
                                    <div class="mt-2 text-sm font-medium text-gray-900">
                                        {{ $this->selectedProcess->created_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase">Deadline</div>
                                    <div class="mt-2 text-sm font-medium text-gray-900">
                                        @if($this->selectedProcess->deadline_at)
                                            {{ $this->selectedProcess->deadline_at->format('M d, Y') }}
                                        @else
                                            No deadline
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if($this->selectedProcess->custom_message)
                                <div class="mb-6">
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Custom Message</h4>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-gray-700">
                                        {{ $this->selectedProcess->custom_message }}
                                    </div>
                                </div>
                            @endif

                            {{-- Signers Timeline --}}
                            <div class="mb-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Signers Timeline</h4>
                                <div class="space-y-4">
                                    @foreach($this->selectedProcess->signers as $signer)
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                @php
                                                    $signerColor = $this->getSignerStatusColor($signer->status);
                                                    $iconColors = [
                                                        'gray' => 'bg-gray-200 text-gray-600',
                                                        'blue' => 'bg-blue-200 text-blue-600',
                                                        'yellow' => 'bg-yellow-200 text-yellow-600',
                                                        'green' => 'bg-green-200 text-green-600',
                                                        'red' => 'bg-red-200 text-red-600',
                                                    ];
                                                @endphp
                                                <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $iconColors[$signerColor] ?? $iconColors['gray'] }}">
                                                    @if($signer->hasSigned())
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="ml-4 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h5 class="text-sm font-medium text-gray-900">{{ $signer->name }}</h5>
                                                        <p class="text-xs text-gray-500">{{ $signer->email }}</p>
                                                    </div>
                                                    @php
                                                        $statusColor = $this->getSignerStatusColor($signer->status);
                                                        $statusColorClasses = [
                                                            'gray' => 'bg-gray-100 text-gray-800',
                                                            'blue' => 'bg-blue-100 text-blue-800',
                                                            'yellow' => 'bg-yellow-100 text-yellow-800',
                                                            'green' => 'bg-green-100 text-green-800',
                                                            'red' => 'bg-red-100 text-red-800',
                                                        ];
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColorClasses[$statusColor] ?? $statusColorClasses['gray'] }}">
                                                        {{ ucfirst($signer->status) }}
                                                    </span>
                                                </div>
                                                <div class="mt-2 text-xs text-gray-500 space-y-1">
                                                    @if($signer->sent_at)
                                                        <div class="flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                            </svg>
                                                            Sent: {{ $signer->sent_at->diffForHumans() }}
                                                        </div>
                                                    @endif
                                                    @if($signer->viewed_at)
                                                        <div class="flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                            </svg>
                                                            Viewed: {{ $signer->viewed_at->diffForHumans() }}
                                                        </div>
                                                    @endif
                                                    @if($signer->signed_at)
                                                        <div class="flex items-center text-green-600">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            Signed: {{ $signer->signed_at->diffForHumans() }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Progress Bar --}}
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                                    <span class="text-sm font-bold text-gray-900">{{ $this->selectedProcess->getCompletionPercentage() }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-gradient-to-r from-purple-600 to-blue-600 h-3 rounded-full transition-all duration-300" 
                                         style="width: {{ $this->selectedProcess->getCompletionPercentage() }}%"></div>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">
                                    {{ $this->selectedProcess->getCompletedSignersCount() }} of {{ $this->selectedProcess->getTotalSignersCount() }} signers have completed
                                </div>
                            </div>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="px-6 py-4 bg-gray-50 flex justify-end">
                            <button wire:click="closeDetails" 
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
