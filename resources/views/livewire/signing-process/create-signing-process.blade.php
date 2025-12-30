<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('Create Signing Process') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Set up a document for electronic signature') }}</p>
    </div>

    {{-- Success Message --}}
    @if($success)
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ $success }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Error Message --}}
    @if($error)
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ __('There are validation errors:') }}</p>
                    <ul class="mt-1 text-sm text-red-700 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit.prevent="create" class="space-y-6">
        {{-- Document Selection --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('1. Select Document') }}</h3>
            
            <div>
                <label for="documentId" class="block text-sm font-medium text-gray-700 mb-2">
                    {{ __('Document to sign') }} <span class="text-red-500">*</span>
                </label>
                <select
                    id="documentId"
                    wire:model.live="documentId"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('documentId') border-red-500 @enderror"
                    required
                >
                    <option value="">{{ __('-- Select a document --') }}</option>
                    @foreach($this->availableDocuments as $doc)
                        <option value="{{ $doc['id'] }}">
                            {{ $doc['name'] }} ({{ $doc['pages'] }} {{ __('pages') }}, {{ $doc['size'] }})
                        </option>
                    @endforeach
                </select>
                @error('documentId')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($this->selectedDocument)
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start space-x-3">
                        <svg class="w-8 h-8 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="font-medium text-blue-900">{{ $this->selectedDocument->original_filename }}</p>
                            <p class="text-sm text-blue-700 mt-1">
                                {{ $this->selectedDocument->getFormattedFileSize() }}
                                • {{ $this->selectedDocument->page_count }} {{ __('pages') }}
                                • {{ __('Uploaded') }} {{ $this->selectedDocument->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Signers Configuration --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('2. Add Signers') }}</h3>
                <button
                    type="button"
                    wire:click="addSigner"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @if(count($signers) >= 10) disabled @endif
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    {{ __('Add Signer') }}
                </button>
            </div>

            <div class="space-y-4">
                @foreach($signers as $index => $signer)
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg" wire:key="signer-{{ $index }}">
                        <div class="flex items-start justify-between mb-3">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-blue-600 rounded-full">
                                {{ $index + 1 }}
                            </span>
                            @if(count($signers) > 1)
                                <button
                                    type="button"
                                    wire:click="removeSigner({{ $index }})"
                                    class="text-red-600 hover:text-red-700 text-sm"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- Name --}}
                            <div>
                                <label for="signer-name-{{ $index }}" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Full Name') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="signer-name-{{ $index }}"
                                    wire:model="signers.{{ $index }}.name"
                                    placeholder="{{ __('John Doe') }}"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('signers.'.$index.'.name') border-red-500 @enderror"
                                    required
                                >
                                @error('signers.'.$index.'.name')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="signer-email-{{ $index }}" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Email') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="signer-email-{{ $index }}"
                                    wire:model="signers.{{ $index }}.email"
                                    placeholder="{{ __('john@example.com') }}"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('signers.'.$index.'.email') border-red-500 @enderror"
                                    required
                                >
                                @error('signers.'.$index.'.email')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Phone --}}
                            <div>
                                <label for="signer-phone-{{ $index }}" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Phone') }} <span class="text-gray-400 text-xs">({{ __('optional') }})</span>
                                </label>
                                <input
                                    type="tel"
                                    id="signer-phone-{{ $index }}"
                                    wire:model="signers.{{ $index }}.phone"
                                    placeholder="{{ __('+34 600 000 000') }}"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('signers.'.$index.'.phone') border-red-500 @enderror"
                                >
                                @error('signers.'.$index.'.phone')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-sm text-gray-500">
                {{ __('Minimum 1 signer, maximum 10 signers') }}
            </p>
        </div>

        {{-- Process Configuration --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('3. Configure Process') }}</h3>
            
            <div class="space-y-4">
                {{-- Signature Order --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('Signing Order') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-start p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors @if($signatureOrder === 'parallel') bg-blue-50 border-blue-500 @endif">
                            <input
                                type="radio"
                                wire:model="signatureOrder"
                                value="parallel"
                                class="mt-1 text-blue-600 focus:ring-blue-500"
                            >
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">{{ __('Parallel') }}</span>
                                <p class="text-sm text-gray-600">{{ __('All signers can sign at the same time') }}</p>
                            </div>
                        </label>
                        <label class="flex items-start p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors @if($signatureOrder === 'sequential') bg-blue-50 border-blue-500 @endif">
                            <input
                                type="radio"
                                wire:model="signatureOrder"
                                value="sequential"
                                class="mt-1 text-blue-600 focus:ring-blue-500"
                            >
                            <div class="ml-3">
                                <span class="font-medium text-gray-900">{{ __('Sequential') }}</span>
                                <p class="text-sm text-gray-600">{{ __('Signers must sign in the order specified above') }}</p>
                            </div>
                        </label>
                    </div>
                    @error('signatureOrder')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Custom Message --}}
                <div>
                    <label for="customMessage" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('Custom Message for Signers') }} <span class="text-gray-400 text-xs">({{ __('optional') }})</span>
                    </label>
                    <textarea
                        id="customMessage"
                        wire:model="customMessage"
                        rows="3"
                        maxlength="500"
                        placeholder="{{ __('Please review and sign this document...') }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('customMessage') border-red-500 @enderror"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1">
                        @error('customMessage')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @else
                            <p class="text-xs text-gray-500">{{ __('Maximum 500 characters') }}</p>
                        @enderror
                        <p class="text-xs text-gray-500">{{ strlen($customMessage ?? '') }}/500</p>
                    </div>
                </div>

                {{-- Deadline --}}
                <div>
                    <label for="deadlineAt" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('Deadline') }} <span class="text-gray-400 text-xs">({{ __('optional') }})</span>
                    </label>
                    <input
                        type="date"
                        id="deadlineAt"
                        wire:model="deadlineAt"
                        min="{{ $this->minDeadlineDate }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('deadlineAt') border-red-500 @enderror"
                    >
                    @error('deadlineAt')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">{{ __('If set, signers must complete before this date') }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <button
                type="button"
                wire:click="resetForm"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @if($creating) disabled @endif
            >
                {{ __('Reset') }}
            </button>

            <button
                type="submit"
                class="inline-flex items-center px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                @if($creating) disabled @endif
            >
                @if($creating)
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('Creating...') }}
                @else
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Create Signing Process') }}
                @endif
            </button>
        </div>
    </form>
</div>

{{-- Alpine.js for redirect after success --}}
@script
<script>
    $wire.on('redirect-to-process', (event) => {
        setTimeout(() => {
            window.location.href = `/signing-processes/${event.uuid}`;
        }, 1500);
    });
</script>
@endscript
