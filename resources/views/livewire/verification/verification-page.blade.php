<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-gray-900">
                Document Verification
            </h1>
            <p class="mt-2 text-gray-600">
                Verify the authenticity and integrity of signed documents
            </p>
        </div>

        {{-- Verification Methods Tabs --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px" aria-label="Tabs">
                    <button
                        wire:click="switchMethod('code')"
                        class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors {{ $method === 'code' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        Verification Code
                    </button>
                    <button
                        wire:click="switchMethod('hash')"
                        class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors {{ $method === 'hash' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Document Hash
                    </button>
                </nav>
            </div>

            <div class="p-6">
                @if ($method === 'code')
                    {{-- Verification by Code --}}
                    <form wire:submit="verifyByCode" class="space-y-4">
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                                Verification Code
                            </label>
                            <input
                                type="text"
                                id="code"
                                wire:model="code"
                                placeholder="XXXX-XXXX-XXXX"
                                class="w-full px-4 py-3 text-lg text-center uppercase tracking-wider border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                maxlength="14"
                            >
                            @error('code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-gray-500">
                                Enter the 12-character code found on the signed document
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="w-full py-3 px-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="verifyByCode">Verify Document</span>
                            <span wire:loading wire:target="verifyByCode" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Verifying...
                            </span>
                        </button>
                    </form>
                @else
                    {{-- Verification by Hash --}}
                    <form wire:submit="verifyByHash" class="space-y-4">
                        <div>
                            <label for="hash" class="block text-sm font-medium text-gray-700 mb-1">
                                Document SHA-256 Hash
                            </label>
                            <textarea
                                id="hash"
                                wire:model="hash"
                                placeholder="Enter the 64-character SHA-256 hash of the document..."
                                rows="3"
                                class="w-full px-4 py-3 text-sm font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            ></textarea>
                            @error('hash')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-gray-500">
                                Calculate the SHA-256 hash of your document and paste it here to verify
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="w-full py-3 px-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="verifyByHash">Verify by Hash</span>
                            <span wire:loading wire:target="verifyByHash" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Verifying...
                            </span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Error Message --}}
        @if ($error)
            <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                    <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            </div>
        @endif

        {{-- Verification Result --}}
        @if ($result)
            <div class="mt-6 bg-white rounded-lg shadow-sm overflow-hidden">
                {{-- Result Header --}}
                <div class="p-6 {{ $result['valid'] ? 'bg-green-50' : 'bg-red-50' }}">
                    <div class="flex items-center">
                        @if ($result['valid'])
                            <div class="flex-shrink-0">
                                <svg class="w-12 h-12 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-xl font-bold text-green-800">Document Verified</h2>
                                <p class="text-green-700">This document is authentic and has not been modified.</p>
                            </div>
                        @else
                            <div class="flex-shrink-0">
                                <svg class="w-12 h-12 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-xl font-bold text-red-800">Verification Failed</h2>
                                <p class="text-red-700">{{ $result['error'] ?? 'The document could not be verified.' }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Confidence Score --}}
                <div class="p-6 border-b">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Confidence Score</span>
                        <span class="text-lg font-bold {{ $result['confidence_level'] === 'high' ? 'text-green-600' : ($result['confidence_level'] === 'medium' ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $result['confidence_score'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div 
                            class="h-3 rounded-full {{ $result['confidence_level'] === 'high' ? 'bg-green-500' : ($result['confidence_level'] === 'medium' ? 'bg-yellow-500' : 'bg-red-500') }}"
                            style="width: {{ $result['confidence_score'] }}%"
                        ></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Confidence Level: 
                        <span class="font-medium uppercase">{{ $result['confidence_level'] }}</span>
                    </p>
                </div>

                {{-- Document Info --}}
                @if ($result['document'])
                    <div class="p-6 border-b">
                        <h3 class="text-sm font-medium text-gray-900 mb-3">Document Information</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs text-gray-500">Filename</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $result['document']['filename'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Pages</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $result['document']['pages'] ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Uploaded</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $result['document']['uploaded_at'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Size</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ number_format($result['document']['size'] / 1024, 2) }} KB</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-gray-500">SHA-256 Hash</dt>
                                <dd class="text-xs font-mono text-gray-700 break-all mt-1">{{ $result['document']['hash'] }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                {{-- Verification Checks --}}
                @if (!empty($result['checks']))
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-900 mb-3">Verification Checks</h3>
                        <ul class="space-y-2">
                            @foreach ($result['checks'] as $check)
                                <li class="flex items-center">
                                    @if ($check['passed'])
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm {{ $check['passed'] ? 'text-gray-700' : 'text-gray-500' }}">
                                        {{ $check['name'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Actions --}}
                @if ($result['valid'] && config('verification.page.allow_download', true))
                    <div class="p-6 bg-gray-50 border-t">
                        <button
                            wire:click="downloadEvidence"
                            class="w-full py-2 px-4 bg-gray-800 text-white font-medium rounded-lg hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors"
                        >
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Download Evidence Dossier
                        </button>
                    </div>
                @endif
            </div>

            {{-- Verify Another --}}
            <div class="mt-6 text-center">
                <button
                    wire:click="resetForm"
                    class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                    Verify another document
                </button>
            </div>
        @endif

        {{-- Info Section --}}
        <div class="mt-10 text-center text-sm text-gray-500">
            <p>
                This verification service confirms the authenticity and integrity of documents
                signed through our platform using cryptographic hashes and timestamps.
            </p>
            <p class="mt-2">
                <a href="#" class="text-blue-600 hover:text-blue-700">Learn more about document verification →</a>
            </p>
        </div>
    </div>
</div>
