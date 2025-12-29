<div class="space-y-6">
    {{-- Upload Area --}}
    <div
        x-data="{
            isDragging: false,
            handleDrop(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type === 'application/pdf') {
                    @this.upload('file', files[0]);
                } else {
                    @this.set('error', 'Please drop a PDF file');
                }
            }
        }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="isDragging = false; handleDrop($event)"
        class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors duration-200"
        :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'"
    >
        @if(!$uploadedDocument)
            <div class="space-y-4">
                {{-- Upload Icon --}}
                <div class="mx-auto w-16 h-16 text-gray-400">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-full h-full">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>

                {{-- Upload Text --}}
                <div>
                    <p class="text-lg font-medium text-gray-700">
                        {{ __('Drag a PDF here or') }}
                    </p>
                    <label class="cursor-pointer text-blue-600 hover:text-blue-700 font-medium">
                        {{ __('select a file') }}
                        <input
                            type="file"
                            wire:model="file"
                            accept=".pdf,application/pdf"
                            class="hidden"
                        >
                    </label>
                </div>

                {{-- Limits Info --}}
                <p class="text-sm text-gray-500">
                    PDF • {{ __('Max') }} {{ $this->maxSizeMb }}MB • {{ __('Up to') }} {{ $this->maxPages }} {{ __('pages') }}
                </p>
            </div>

            {{-- Loading Overlay --}}
            <div wire:loading wire:target="file" class="absolute inset-0 bg-white/80 flex items-center justify-center rounded-lg">
                <div class="flex items-center space-x-2">
                    <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-gray-600">{{ __('Validating file...') }}</span>
                </div>
            </div>

            {{-- Uploading Overlay --}}
            @if($uploading)
            <div class="absolute inset-0 bg-white/90 flex items-center justify-center rounded-lg">
                <div class="text-center space-y-3">
                    <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <p class="text-gray-600 font-medium">{{ __('Processing document...') }}</p>
                    <p class="text-sm text-gray-500">{{ __('Validating, hashing, and encrypting') }}</p>
                    <button
                        wire:click="cancelUpload"
                        class="text-sm text-red-600 hover:text-red-700"
                    >
                        {{ __('Cancel') }}
                    </button>
                </div>
            </div>
            @endif
        @else
            {{-- Uploaded Document Preview --}}
            <div class="flex items-start space-x-4 text-left">
                @if($uploadedDocument['thumbnail_url'])
                    <img src="{{ $uploadedDocument['thumbnail_url'] }}"
                         alt="{{ __('Preview') }}"
                         class="w-24 h-32 object-cover rounded shadow">
                @else
                    <div class="w-24 h-32 bg-gray-100 rounded flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                @endif

                <div class="flex-1 min-w-0">
                    <h3 class="font-medium text-gray-900 truncate" title="{{ $uploadedDocument['name'] }}">
                        {{ $uploadedDocument['name'] }}
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $uploadedDocument['formatted_size'] }}
                        @if($uploadedDocument['pages'])
                            • {{ $uploadedDocument['pages'] }} {{ __('pages') }}
                        @endif
                    </p>

                    {{-- Status Badges --}}
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ __('Uploaded') }}
                        </span>

                        @if($uploadedDocument['is_pdf_a'])
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded">
                                PDF/A
                            </span>
                        @endif

                        @if($uploadedDocument['has_signatures'])
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                                {{ __('Signed') }}
                            </span>
                        @endif

                        @if($uploadedDocument['has_javascript'])
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded">
                                {{ __('Contains JS') }}
                            </span>
                        @endif
                    </div>

                    {{-- Hash Display --}}
                    <div class="mt-2">
                        <p class="text-xs text-gray-500">SHA-256:</p>
                        <code class="text-xs text-gray-600 font-mono break-all">
                            {{ $uploadedDocument['hash'] }}
                        </code>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-3 flex items-center space-x-3">
                        <a
                            href="{{ $uploadedDocument['download_url'] }}"
                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                        >
                            {{ __('Download') }}
                        </a>
                        <button
                            wire:click="removeFile"
                            class="text-sm text-red-600 hover:text-red-700"
                        >
                            {{ __('Upload another') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Error Message --}}
    @if($error)
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-2">
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @error('file')
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <p class="ml-2 text-sm text-red-700">{{ $message }}</p>
            </div>
        </div>
    @enderror

    {{-- Warnings --}}
    @if(!empty($warnings))
        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-2">
                    <p class="text-sm font-medium text-yellow-800">{{ __('Warnings') }}</p>
                    <ul class="mt-1 text-sm text-yellow-700 list-disc list-inside">
                        @foreach($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Upload Button (shown when file is selected but not uploaded) --}}
    @if($file && !$uploadedDocument && !$uploading)
        <div class="flex justify-end">
            <button
                wire:click="upload"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                {{ __('Upload Document') }}
            </button>
        </div>
    @endif

    {{-- Recent Documents --}}
    @if($this->recentDocuments->count() > 0 && !$uploadedDocument)
        <div class="border-t pt-6">
            <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Recent Documents') }}</h3>
            <div class="space-y-2">
                @foreach($this->recentDocuments as $document)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center space-x-3 min-w-0">
                            <svg class="w-8 h-8 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $document->original_filename }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $document->getFormattedFileSize() }}
                                    • {{ $document->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                        <a
                            href="{{ route('documents.show', $document) }}"
                            class="text-sm text-blue-600 hover:text-blue-700 flex-shrink-0"
                        >
                            {{ __('View') }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
