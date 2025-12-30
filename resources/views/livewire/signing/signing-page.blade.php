<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Loading State --}}
        @if ($isLoading)
            <div class="text-center py-20">
                <svg class="animate-spin h-12 w-12 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-gray-600">Loading signature request...</p>
            </div>
        @endif

        {{-- Error State --}}
        @if ($this->hasError)
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6 bg-red-50">
                        <div class="flex items-center">
                            <svg class="w-12 h-12 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-4">
                                <h2 class="text-xl font-bold text-red-800">Unable to Access Signature Request</h2>
                                <p class="text-red-700 mt-1">{{ $this->errorMessage }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600">
                            If you believe this is an error, please contact the person who sent you this signature request.
                        </p>
                        <div class="mt-6">
                            <a href="/" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Return to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Success State - Signature Request Loaded --}}
        @if ($this->signer && !$this->hasError)
            <div class="space-y-6">
                
                {{-- Header with Gradient --}}
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold">Document Signature Request</h1>
                            <p class="mt-2 text-blue-100">
                                You have been requested to sign the following document
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <svg class="w-16 h-16 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Left Column: Document Info --}}
                    <div class="lg:col-span-2 space-y-6">
                        
                        {{-- Document Preview Card --}}
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 border-b">
                                <h2 class="text-lg font-semibold text-gray-900">Document to Sign</h2>
                            </div>
                            <div class="p-6">
                                @if ($this->document)
                                    <div class="space-y-4">
                                        <div>
                                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</label>
                                            <p class="mt-1 text-lg font-medium text-gray-900">{{ $this->document->original_filename }}</p>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-4">
                                            @if ($this->document->metadata && isset($this->document->metadata['pages']))
                                            <div>
                                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Pages</label>
                                                <p class="mt-1 text-sm text-gray-900">{{ $this->document->metadata['pages'] }}</p>
                                            </div>
                                            @endif
                                            <div>
                                                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Size</label>
                                                <p class="mt-1 text-sm text-gray-900">{{ number_format($this->document->file_size / 1024, 2) }} KB</p>
                                            </div>
                                        </div>

                                        {{-- PDF Preview (Simple MVP) --}}
                                        <div class="mt-6">
                                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2 block">Document Preview</label>
                                            <div class="border-2 border-gray-200 rounded-lg overflow-hidden bg-gray-100">
                                                <div class="aspect-[8.5/11] flex items-center justify-center text-gray-400">
                                                    <div class="text-center">
                                                        <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                        </svg>
                                                        <p class="text-sm">PDF Preview</p>
                                                        <p class="text-xs mt-1">Full preview will be available after OTP verification</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Custom Message (if exists) --}}
                        @if ($this->process && $this->process->custom_message)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <div class="flex">
                                <svg class="w-6 h-6 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                </svg>
                                <div>
                                    <h3 class="font-semibold text-blue-900 mb-1">Message from {{ $this->promoter->name ?? 'the sender' }}</h3>
                                    <p class="text-blue-800">{{ $this->process->custom_message }}</p>
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>

                    {{-- Right Column: Signer Info & Actions --}}
                    <div class="space-y-6">
                        
                        {{-- Signer Info Card --}}
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 border-b">
                                <h2 class="text-lg font-semibold text-gray-900">Signature Details</h2>
                            </div>
                            <div class="p-6 space-y-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Your Name</label>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $this->signer->name }}</p>
                                </div>
                                
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $this->signer->email }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Your Position</label>
                                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $this->signer->order }} of {{ $this->totalSigners }}</p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Signing Order</label>
                                        <p class="mt-1 text-sm text-gray-900">
                                            @if ($this->process->isSequential())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    Sequential
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    Parallel
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if ($this->process->deadline_at)
                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</label>
                                    <p class="mt-1 text-sm text-red-600 font-medium">
                                        {{ $this->process->deadline_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                @endif

                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Requested by</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $this->promoter->name ?? 'N/A' }}</p>
                                </div>

                                <div>
                                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</label>
                                    <div class="mt-2">
                                        <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                            <span>{{ $this->completedSigners }} of {{ $this->totalSigners }} signed</span>
                                            <span>{{ $this->totalSigners > 0 ? round(($this->completedSigners / $this->totalSigners) * 100) : 0 }}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $this->totalSigners > 0 ? ($this->completedSigners / $this->totalSigners) * 100 : 0 }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Card --}}
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="p-6">
                                @if ($this->alreadySigned)
                                    {{-- Already Signed --}}
                                    <div class="text-center py-4">
                                        <svg class="w-16 h-16 mx-auto text-green-500 mb-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Document Signed</h3>
                                        <p class="text-sm text-gray-600">
                                            You signed this document on<br>
                                            <strong>{{ $this->signer->signed_at->format('d/m/Y \a\t H:i') }}</strong>
                                        </p>
                                    </div>
                                @elseif (!$this->canSign && $this->waitingFor)
                                    {{-- Not Your Turn --}}
                                    <div class="text-center py-4">
                                        <svg class="w-16 h-16 mx-auto text-yellow-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Please Wait</h3>
                                        <p class="text-sm text-gray-600">
                                            Waiting for <strong>{{ $this->waitingFor }}</strong> to sign first.
                                        </p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            This document requires sequential signatures.
                                        </p>
                                    </div>
                                @elseif ($this->canSign)
                                    {{-- OTP Verification Flow --}}
                                    
                                    @if ($this->hasVerifiedOtp)
                                        {{-- OTP Verified - Signature Interface --}}
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900 mb-4 text-center">Create Your Signature</h3>
                                            
                                            {{-- Status Messages --}}
                                            @if ($signatureMessage)
                                                <div class="mb-4 p-3 rounded-lg {{ $signatureError ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                                                    <p class="text-sm {{ $signatureError ? 'text-red-800' : 'text-green-800' }}">
                                                        {{ $signatureMessage }}
                                                    </p>
                                                </div>
                                            @endif

                                            {{-- Signature Type Tabs --}}
                                            <div class="flex border-b border-gray-200 mb-4">
                                                <button
                                                    wire:click="setSignatureType('draw')"
                                                    class="flex-1 py-2 px-4 text-center border-b-2 font-medium text-sm transition-colors {{ $signatureType === 'draw' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                                                >
                                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                    </svg>
                                                    Draw
                                                </button>
                                                <button
                                                    wire:click="setSignatureType('type')"
                                                    class="flex-1 py-2 px-4 text-center border-b-2 font-medium text-sm transition-colors {{ $signatureType === 'type' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                                                >
                                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Type
                                                </button>
                                                <button
                                                    wire:click="setSignatureType('upload')"
                                                    class="flex-1 py-2 px-4 text-center border-b-2 font-medium text-sm transition-colors {{ $signatureType === 'upload' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                                                >
                                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                    Upload
                                                </button>
                                            </div>

                                            {{-- Draw Signature --}}
                                            @if ($signatureType === 'draw')
                                                <div x-data="window.signatureCanvas()" class="space-y-3">
                                                    <div class="border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-white">
                                                        <canvas
                                                            x-ref="canvas"
                                                            class="w-full cursor-crosshair touch-none"
                                                            style="height: 200px;"
                                                        ></canvas>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button
                                                            @click="clear()"
                                                            type="button"
                                                            class="flex-1 py-2 px-4 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
                                                        >
                                                            Clear
                                                        </button>
                                                        <button
                                                            @click="$wire.signatureData = getDataURL()"
                                                            type="button"
                                                            class="flex-1 py-2 px-4 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                                                        >
                                                            Confirm Signature
                                                        </button>
                                                    </div>
                                                    <p class="text-xs text-gray-500 text-center">
                                                        Draw your signature using your mouse or finger
                                                    </p>
                                                </div>
                                            @endif

                                            {{-- Type Signature --}}
                                            @if ($signatureType === 'type')
                                                <div class="space-y-3">
                                                    <input
                                                        type="text"
                                                        wire:model.live="typedSignature"
                                                        placeholder="Type your full name"
                                                        maxlength="100"
                                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                                    />
                                                    @if (!empty($typedSignature))
                                                        <div class="border-2 border-gray-200 rounded-lg bg-white p-6 text-center">
                                                            <p class="text-4xl font-['Dancing_Script',cursive] text-gray-900" style="font-family: 'Dancing Script', cursive;">
                                                                {{ $typedSignature }}
                                                            </p>
                                                        </div>
                                                    @else
                                                        <div class="border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 p-6 text-center">
                                                            <p class="text-gray-400 text-sm">Preview will appear here</p>
                                                        </div>
                                                    @endif
                                                    <p class="text-xs text-gray-500 text-center">
                                                        Your typed signature will be converted to a handwritten style
                                                    </p>
                                                </div>
                                            @endif

                                            {{-- Upload Signature --}}
                                            @if ($signatureType === 'upload')
                                                <div class="space-y-3">
                                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                                        @if ($signatureData && str_starts_with($signatureData, 'data:image'))
                                                            <img src="{{ $signatureData }}" alt="Signature" class="max-h-32 mx-auto mb-3">
                                                            <button
                                                                wire:click="clearSignature"
                                                                type="button"
                                                                class="text-sm text-red-600 hover:text-red-700 font-medium"
                                                            >
                                                                Remove Image
                                                            </button>
                                                        @else
                                                            <input
                                                                type="file"
                                                                wire:model="uploadedSignature"
                                                                accept="image/png,image/jpeg,image/jpg"
                                                                class="hidden"
                                                                id="signature-upload"
                                                            />
                                                            <label for="signature-upload" class="cursor-pointer">
                                                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                                </svg>
                                                                <p class="text-sm text-gray-600 mb-1">
                                                                    <span class="text-purple-600 font-medium">Click to upload</span> or drag and drop
                                                                </p>
                                                                <p class="text-xs text-gray-500">PNG or JPG (max 2MB)</p>
                                                            </label>
                                                            @if ($uploadedSignature)
                                                                <div class="mt-3">
                                                                    <div wire:loading wire:target="uploadedSignature" class="text-sm text-gray-600">
                                                                        Processing image...
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Consent Checkbox --}}
                                            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                                <label class="flex items-start cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="consentGiven"
                                                        class="mt-1 h-5 w-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                                                    />
                                                    <span class="ml-3 text-sm text-gray-700">
                                                        I consent to electronically sign this document. I understand this signature has the same legal validity as a handwritten signature.
                                                    </span>
                                                </label>
                                            </div>

                                            {{-- Sign Document Button --}}
                                            <button
                                                wire:click="signDocument"
                                                wire:loading.attr="disabled"
                                                wire:target="signDocument"
                                                @disabled(!$consentGiven || empty($signatureData) || $isSigning)
                                                class="w-full mt-4 py-3 px-4 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <span wire:loading.remove wire:target="signDocument">
                                                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    Sign Document
                                                </span>
                                                <span wire:loading wire:target="signDocument">
                                                    <svg class="animate-spin h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Signing...
                                                </span>
                                            </button>
                                        </div>
                                    
                                    @else
                                        {{-- OTP Not Verified Yet --}}
                                        <div>
                                            {{-- Status Messages --}}
                                            @if ($otpMessage)
                                                <div class="mb-4 p-3 rounded-lg {{ $otpError ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                                                    <p class="text-sm {{ $otpError ? 'text-red-800' : 'text-green-800' }}">
                                                        {{ $otpMessage }}
                                                    </p>
                                                </div>
                                            @endif

                                            @if (!$otpRequested)
                                                {{-- Step 1: Request OTP --}}
                                                <div>
                                                    <button
                                                        wire:click="requestOtp"
                                                        class="w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-105 shadow-lg"
                                                    >
                                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                        </svg>
                                                        Request Verification Code
                                                    </button>
                                                    <p class="mt-3 text-xs text-center text-gray-500">
                                                        To continue, request a verification code that will be sent to your email
                                                    </p>
                                                </div>
                                            @else
                                                {{-- Step 2: Enter OTP Code --}}
                                                <div>
                                                    <label for="otpCode" class="block text-sm font-medium text-gray-700 mb-2">
                                                        Enter Verification Code
                                                    </label>
                                                    <input
                                                        type="text"
                                                        id="otpCode"
                                                        wire:model="otpCode"
                                                        inputmode="numeric"
                                                        maxlength="6"
                                                        placeholder="000000"
                                                        class="w-full px-4 py-3 text-center text-2xl font-mono font-bold tracking-widest border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                    />
                                                    
                                                    <button
                                                        wire:click="verifyOtp"
                                                        class="w-full mt-4 py-3 px-4 bg-gradient-to-r from-green-600 to-blue-600 text-white font-semibold rounded-lg hover:from-green-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-lg"
                                                    >
                                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        Verify Code
                                                    </button>

                                                    <div class="mt-4 text-center">
                                                        <button
                                                            wire:click="requestOtp"
                                                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                                                        >
                                                            Didn't receive it? Request new code
                                                        </button>
                                                    </div>
                                                    
                                                    <p class="mt-3 text-xs text-center text-gray-500">
                                                        Check your email for the 6-digit verification code
                                                    </p>
                                                </div>
                                            @endif

                                            {{-- Decline Button --}}
                                            <div class="mt-6 pt-4 border-t border-gray-200">
                                                <button
                                                    wire:click="declineSignature"
                                                    class="w-full py-2 px-4 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 transition-colors"
                                                >
                                                    Decline to Sign
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                {{-- Security Notice --}}
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex">
                                        <svg class="w-5 h-5 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        <div>
                                            <p class="text-xs font-medium text-gray-700">Secure Signature Process</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Your signature is legally binding and will be recorded with cryptographic evidence.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Info Card --}}
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h3 class="text-sm font-semibold text-blue-900 mb-2">About This Signature Request</h3>
                            <ul class="text-xs text-blue-800 space-y-1">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-1 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Email verification required
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-1 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Timestamped audit trail
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-1 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Legally binding signature
                                </li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
