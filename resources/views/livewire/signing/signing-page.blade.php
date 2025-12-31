<div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50">
    
    {{-- Loading State --}}
    @if ($isLoading)
        <div class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <svg class="animate-spin h-12 w-12 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-gray-600">Cargando solicitud de firma...</p>
            </div>
        </div>
    @endif

    {{-- Error State --}}
    @if ($this->hasError)
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="max-w-md w-full">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="p-6 bg-red-50 text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-red-800">No se puede acceder</h2>
                        <p class="text-red-600 mt-2">{{ $this->errorMessage }}</p>
                    </div>
                    <div class="p-6 text-center">
                        <p class="text-sm text-gray-600 mb-4">
                            Si crees que es un error, contacta con quien te envió esta solicitud.
                        </p>
                        <a href="/" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Volver al inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Signing Flow --}}
    @if ($this->signer && !$this->hasError)
        
        {{-- Header: Only visible on desktop, mobile has its own integrated headers --}}
        <header class="hidden md:flex sticky top-0 z-40 bg-white border-b border-gray-200">
            <div class="px-4 py-3 w-full">
                <div class="flex items-center justify-between">
                    {{-- Logo/Brand --}}
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <span class="font-bold text-gray-900">Firmalum</span>
                    </div>
                    
                    {{-- Document info --}}
                    <div class="flex-1 min-w-0 mx-4">
                        <h1 class="text-sm font-medium text-gray-900 truncate text-right md:text-left">
                            {{ Str::limit($this->document->original_filename ?? 'Documento', 30) }}
                        </h1>
                    </div>
                    
                    {{-- Status badge --}}
                    <div class="flex-shrink-0">
                        @if ($this->alreadySigned)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Firmado
                            </span>
                        @elseif ($this->hasVerifiedOtp)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Verificado
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        {{-- Already Signed State --}}
        @if ($this->alreadySigned)
            <div class="flex items-center justify-center min-h-[80vh] px-4">
                <div class="max-w-md w-full text-center">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Documento Firmado</h2>
                    <p class="text-gray-600 mb-6">
                        Firmaste este documento el<br>
                        <strong>{{ $this->signer->signed_at->format('d/m/Y \a \l\a\s H:i') }}</strong>
                    </p>
                    <div class="bg-gray-50 rounded-xl p-4 text-left text-sm">
                        <div class="flex items-center text-gray-600 mb-2">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Firma registrada con evidencia criptográfica
                        </div>
                        <div class="flex items-center text-gray-600">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                            Sellado con marca de tiempo oficial
                        </div>
                    </div>
                </div>
            </div>

        {{-- Waiting for Previous Signer --}}
        @elseif (!$this->canSign && $this->waitingFor)
            <div class="flex items-center justify-center min-h-[80vh] px-4">
                <div class="max-w-md w-full text-center">
                    <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Esperando tu turno</h2>
                    <p class="text-gray-600 mb-6">
                        <strong>{{ $this->waitingFor }}</strong> debe firmar antes.
                    </p>
                    <p class="text-sm text-gray-500">
                        Te notificaremos por email cuando sea tu turno.
                    </p>
                </div>
            </div>

        {{-- Can Sign Flow --}}
        @elseif ($this->canSign)
            
            {{-- Step Progress Indicator - Hidden on mobile --}}
            <div class="hidden md:block bg-white border-b border-gray-100 px-4 py-3">
                <div class="flex items-center justify-center gap-2">
                    @php
                        // Flow: Info → Leer → Verificar → Firmar
                        // Steps 1-2 are combined in the reading view
                        if (!$hasReadDocument) {
                            $currentStep = $documentRead ? 2 : 1; // Shows progress as user reads
                        } elseif (!$this->hasVerifiedOtp) {
                            $currentStep = 3; // OTP verification
                        } else {
                            $currentStep = 4; // Signing
                        }
                    @endphp
                    
                    @foreach ([1 => 'Info', 2 => 'Leer', 3 => 'Verificar', 4 => 'Firmar'] as $step => $label)
                        <div class="flex items-center">
                            <div class="flex flex-col items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors
                                    {{ $step < $currentStep ? 'bg-green-500 text-white' : ($step === $currentStep ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                                    @if ($step < $currentStep)
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        {{ $step }}
                                    @endif
                                </div>
                                <span class="text-xs mt-1 {{ $step === $currentStep ? 'text-blue-600 font-medium' : 'text-gray-400' }}">{{ $label }}</span>
                            </div>
                            @if ($step < 4)
                                <div class="w-8 h-0.5 mx-1 {{ $step < $currentStep ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- STEP 1-2: Info + Read Document (before OTP) --}}
            @if (!$hasReadDocument)
                {{-- MOBILE VIEW: Ultra-clean fullscreen document (outside main padding) --}}
                <div class="md:hidden fixed inset-0 flex flex-col bg-gray-100 z-50">
                    {{-- Header --}}
                    <div class="flex-shrink-0 bg-gray-100 px-4 pt-4 pb-3 safe-area-top">
                        <div class="bg-white rounded-2xl shadow-sm px-4 py-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                    </div>
                                    <span class="font-bold text-gray-900 text-lg">Firmalum</span>
                                </div>
                                <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">Firma digital</span>
                            </div>
                        </div>
                    </div>

                    {{-- PDF Viewer - Full available space between header and footer --}}
                    <div class="flex-1 min-h-0 px-4 overflow-hidden" id="pdf-container">
                        <div id="pdf-viewer" class="w-full h-full bg-gray-900 rounded-2xl overflow-auto"></div>
                    </div>

                    {{-- Fixed Bottom Action Bar --}}
                    <div class="flex-shrink-0 bg-gray-100 px-4 pt-3 pb-4 safe-area-bottom">
                        <div class="bg-white rounded-2xl shadow-sm px-4 py-4">
                            <label class="flex items-center cursor-pointer mb-3">
                                <input
                                    type="checkbox"
                                    wire:model="documentRead"
                                    class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <span class="ml-3 text-sm text-gray-700">
                                    He leído el documento
                                </span>
                            </label>
                            
                            <button
                                wire:click="proceedToVerification"
                                @disabled(!$documentRead)
                                class="w-full py-3.5 px-6 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                Continuar
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- MOBILE HEADER for Steps 3-4 (when hasReadDocument is true) --}}
            @if ($hasReadDocument)
                <div class="md:hidden bg-white border-b border-gray-200 px-4 py-3 safe-area-top">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <span class="font-semibold text-gray-900 text-sm">Firmalum</span>
                    </div>
                </div>
            @endif

            <main class="px-4 py-4 md:py-6">
                
                {{-- Status Messages --}}
                @if ($otpMessage)
                    <div class="mb-4 p-4 rounded-xl {{ $otpError ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                        <p class="text-sm {{ $otpError ? 'text-red-800' : 'text-green-800' }}">{{ $otpMessage }}</p>
                    </div>
                @endif
                
                @if ($signatureMessage)
                    <div class="mb-4 p-4 rounded-xl {{ $signatureError ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                        <p class="text-sm {{ $signatureError ? 'text-red-800' : 'text-green-800' }}">{{ $signatureMessage }}</p>
                    </div>
                @endif

                {{-- STEP 1-2: Desktop View --}}
                @if (!$hasReadDocument)
                    <div class="hidden md:block max-w-4xl mx-auto">
                        {{-- Document Info Card --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                            <div class="p-5">
                                <div class="flex items-start gap-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-gray-900 mb-1">{{ $this->document->original_filename }}</h3>
                                        <p class="text-sm text-gray-500">
                                            {{ number_format($this->document->file_size / 1024, 0) }} KB
                                            @if ($this->document->metadata && isset($this->document->metadata['pages']))
                                                · {{ $this->document->metadata['pages'] }} páginas
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Solicitado por <strong>{{ $this->promoter->name ?? 'N/A' }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            @if ($this->process && $this->process->custom_message)
                                <div class="px-5 pb-5">
                                    <div class="bg-blue-50 rounded-xl p-4">
                                        <p class="text-sm text-gray-600 italic">"{{ $this->process->custom_message }}"</p>
                                        <p class="text-xs text-gray-400 mt-2">— {{ $this->promoter->name ?? 'Remitente' }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Signer Info --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Tu información</h3>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="text-gray-900">{{ $this->signer->name }}</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-gray-900">{{ $this->signer->email }}</span>
                                </div>
                                @if ($this->process->deadline_at)
                                    <div class="flex items-center text-orange-600">
                                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>Fecha límite: {{ $this->process->deadline_at->format('d/m/Y') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- PDF Viewer (Desktop) --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                            <div class="bg-gray-100 px-3 py-2 flex items-center justify-between border-b">
                                <span class="text-xs font-medium text-gray-600">📄 Lee el documento</span>
                                <a href="{{ route('documents.preview', $this->document) }}" 
                                   target="_blank"
                                   class="p-1.5 text-gray-500 hover:bg-gray-200 rounded-lg transition-colors flex items-center gap-1 text-xs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Nueva pestaña
                                </a>
                            </div>
                            
                            <div style="height: 60vh; min-height: 400px;">
                                <iframe 
                                    src="{{ route('documents.preview', $this->document) }}#toolbar=0&navpanes=0&scrollbar=1" 
                                    class="w-full h-full"
                                    style="border: none;"
                                ></iframe>
                            </div>
                        </div>

                        {{-- Action Card (Desktop) --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                            <label class="flex items-center cursor-pointer mb-4">
                                <input
                                    type="checkbox"
                                    wire:model="documentRead"
                                    class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <span class="ml-3 text-sm text-gray-700">
                                    He leído y comprendido el contenido del documento
                                </span>
                            </label>
                            
                            <button
                                wire:click="proceedToVerification"
                                @disabled(!$documentRead)
                                class="w-full py-3.5 px-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span class="flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    Continuar con la verificación
                                </span>
                            </button>
                            <p class="text-center text-xs text-gray-500 mt-3">
                                Para firmar necesitarás verificar tu identidad con un código por email
                            </p>
                        </div>
                    </div>

                {{-- STEP 3: OTP Verification --}}
                @elseif (!$this->hasVerifiedOtp)
                    <div class="max-w-lg mx-auto">
                        @if (!$otpRequested)
                            {{-- Request OTP --}}
                            <div class="text-center mb-8">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900 mb-2">Verificación de identidad</h2>
                                <p class="text-gray-600">
                                    Para firmar el documento, necesitamos verificar tu identidad enviando un código a tu email.
                                </p>
                            </div>

                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                                <div class="flex items-center gap-3 text-gray-700">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span>{{ $this->signer->email }}</span>
                                </div>
                            </div>

                            <button
                                wire:click="requestOtp"
                                wire:loading.attr="disabled"
                                class="w-full py-4 px-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-lg disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="requestOtp" class="flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Enviar código de verificación
                                </span>
                                <span wire:loading wire:target="requestOtp" class="flex items-center justify-center">
                                    <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Enviando...
                                </span>
                            </button>

                            <button
                                wire:click="backToReading"
                                class="w-full mt-4 py-3 px-4 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50"
                            >
                                ← Volver al documento
                            </button>
                        @else
                            {{-- Verify OTP Code --}}
                            <div class="text-center mb-8">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900 mb-2">Código enviado</h2>
                                <p class="text-gray-600">
                                    Hemos enviado un código de 6 dígitos a<br>
                                    <strong>{{ $this->signer->email }}</strong>
                                </p>
                            </div>

                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3 text-center">
                                    Introduce el código de verificación
                                </label>
                                <input
                                    type="text"
                                    wire:model="otpCode"
                                    inputmode="numeric"
                                    maxlength="6"
                                    placeholder="000000"
                                    class="w-full px-4 py-4 text-center text-3xl font-mono font-bold tracking-[0.5em] border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    autofocus
                                />
                            </div>

                            <button
                                wire:click="verifyOtp"
                                wire:loading.attr="disabled"
                                class="w-full py-4 px-6 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold rounded-xl hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-lg disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="verifyOtp">Verificar y continuar</span>
                                <span wire:loading wire:target="verifyOtp">Verificando...</span>
                            </button>

                            <div class="text-center mt-4 space-y-2">
                                <button
                                    wire:click="requestOtp"
                                    class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    ¿No lo recibiste? Enviar de nuevo
                                </button>
                            </div>

                            <button
                                wire:click="backToReading"
                                class="w-full mt-4 py-3 px-4 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50"
                            >
                                ← Volver al documento
                            </button>
                        @endif
                    </div>

                {{-- STEP 4: Sign Document (after OTP verified) --}}
                @else
                    <div class="max-w-lg mx-auto" x-data="{ signatureType: @entangle('signatureType') }">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 mb-1">¡Identidad verificada!</h2>
                            <p class="text-gray-600 text-sm">Crea tu firma para completar el proceso</p>
                        </div>

                        {{-- Signature Type Selector --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                            <div class="grid grid-cols-3 border-b border-gray-100">
                                <button
                                    wire:click="setSignatureType('draw')"
                                    class="py-4 text-center transition-colors {{ $signatureType === 'draw' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:bg-gray-50' }}"
                                >
                                    <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                    <span class="text-xs font-medium">Dibujar</span>
                                </button>
                                <button
                                    wire:click="setSignatureType('type')"
                                    class="py-4 text-center transition-colors {{ $signatureType === 'type' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:bg-gray-50' }}"
                                >
                                    <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span class="text-xs font-medium">Escribir</span>
                                </button>
                                <button
                                    wire:click="setSignatureType('upload')"
                                    class="py-4 text-center transition-colors {{ $signatureType === 'upload' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:bg-gray-50' }}"
                                >
                                    <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-xs font-medium">Subir</span>
                                </button>
                            </div>

                            <div class="p-5">
                                {{-- Draw Signature --}}
                                @if ($signatureType === 'draw')
                                    <div x-data="signaturePad()" x-init="init()" class="space-y-4">
                                        <div class="border-2 border-dashed border-gray-300 rounded-xl overflow-hidden bg-white relative">
                                            <canvas
                                                x-ref="canvas"
                                                @touchstart.prevent="startDrawing($event)"
                                                @touchmove.prevent="draw($event)"
                                                @touchend="stopDrawing()"
                                                @mousedown="startDrawing($event)"
                                                @mousemove="draw($event)"
                                                @mouseup="stopDrawing()"
                                                @mouseleave="stopDrawing()"
                                                class="w-full touch-none cursor-crosshair"
                                                style="height: 200px;"
                                            ></canvas>
                                            <div x-show="isEmpty" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                <p class="text-gray-400 text-sm">Dibuja aquí tu firma</p>
                                            </div>
                                        </div>
                                        <div class="flex gap-3">
                                            <button
                                                @click="clear()"
                                                type="button"
                                                class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50"
                                            >
                                                Borrar
                                            </button>
                                            <button
                                                @click="save()"
                                                type="button"
                                                class="flex-1 py-3 px-4 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700"
                                            >
                                                Usar esta firma
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- Type Signature --}}
                                @if ($signatureType === 'type')
                                    <div class="space-y-4">
                                        <input
                                            type="text"
                                            wire:model.live="typedSignature"
                                            placeholder="Escribe tu nombre completo"
                                            maxlength="100"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        />
                                        @if (!empty($typedSignature))
                                            <div class="border-2 border-gray-200 rounded-xl bg-white p-6 text-center">
                                                <p class="text-4xl text-gray-900" style="font-family: 'Dancing Script', cursive;">
                                                    {{ $typedSignature }}
                                                </p>
                                            </div>
                                            <button
                                                wire:click="useTypedSignature"
                                                class="w-full py-3 px-4 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700"
                                            >
                                                Usar esta firma
                                            </button>
                                        @else
                                            <div class="border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 p-6 text-center">
                                                <p class="text-gray-400 text-sm">Vista previa de tu firma</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Upload Signature --}}
                                @if ($signatureType === 'upload')
                                    <div class="space-y-4">
                                        @if ($signatureData && str_starts_with($signatureData, 'data:image'))
                                            <div class="border-2 border-gray-200 rounded-xl p-6 text-center bg-white">
                                                <img src="{{ $signatureData }}" alt="Firma" class="max-h-32 mx-auto">
                                            </div>
                                            <button
                                                wire:click="clearSignature"
                                                class="w-full py-3 px-4 border border-red-300 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50"
                                            >
                                                Eliminar imagen
                                            </button>
                                        @else
                                            <label class="block border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer hover:border-blue-400 transition-colors">
                                                <input
                                                    type="file"
                                                    wire:model="uploadedSignature"
                                                    accept="image/png,image/jpeg,image/jpg"
                                                    class="hidden"
                                                />
                                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                                <p class="text-sm text-gray-600">
                                                    <span class="text-blue-600 font-medium">Pulsa para subir</span>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">PNG o JPG (máx 2MB)</p>
                                            </label>
                                            <div wire:loading wire:target="uploadedSignature" class="text-center text-sm text-gray-600">
                                                Procesando imagen...
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Signature Preview & Confirm --}}
                        @if ($signatureData)
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                                <h3 class="text-sm font-medium text-gray-700 mb-3">Tu firma</h3>
                                <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 mb-4">
                                    <img src="{{ $signatureData }}" alt="Tu firma" class="max-h-24 mx-auto">
                                </div>
                                
                                {{-- Consent --}}
                                <label class="flex items-start cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="consentGiven"
                                        class="mt-1 h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ml-3 text-sm text-gray-700">
                                        Acepto firmar electrónicamente este documento. Entiendo que esta firma tiene la misma validez legal que una firma manuscrita.
                                    </span>
                                </label>
                            </div>

                            {{-- Sign Button --}}
                            <button
                                wire:click="signDocument"
                                wire:loading.attr="disabled"
                                wire:target="signDocument"
                                @disabled(!$consentGiven || $isSigning)
                                class="w-full py-4 px-6 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold rounded-xl hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed text-lg"
                            >
                                <span wire:loading.remove wire:target="signDocument" class="flex items-center justify-center">
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Firmar documento
                                </span>
                                <span wire:loading wire:target="signDocument" class="flex items-center justify-center">
                                    <svg class="animate-spin h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Firmando...
                                </span>
                            </button>
                        @endif
                    </div>
                @endif
            </main>
        @endif
    @endif

    {{-- Signature Pad Alpine Component --}}
    <script>
        function signaturePad() {
            return {
                canvas: null,
                ctx: null,
                isDrawing: false,
                isEmpty: true,
                lastX: 0,
                lastY: 0,
                
                init() {
                    this.canvas = this.$refs.canvas;
                    this.ctx = this.canvas.getContext('2d');
                    this.resizeCanvas();
                    window.addEventListener('resize', () => this.resizeCanvas());
                },
                
                resizeCanvas() {
                    const rect = this.canvas.getBoundingClientRect();
                    const dpr = window.devicePixelRatio || 1;
                    this.canvas.width = rect.width * dpr;
                    this.canvas.height = rect.height * dpr;
                    this.ctx.scale(dpr, dpr);
                    this.ctx.strokeStyle = '#1f2937';
                    this.ctx.lineWidth = 2;
                    this.ctx.lineCap = 'round';
                    this.ctx.lineJoin = 'round';
                },
                
                getCoords(e) {
                    const rect = this.canvas.getBoundingClientRect();
                    if (e.touches && e.touches.length > 0) {
                        return {
                            x: e.touches[0].clientX - rect.left,
                            y: e.touches[0].clientY - rect.top
                        };
                    }
                    return {
                        x: e.clientX - rect.left,
                        y: e.clientY - rect.top
                    };
                },
                
                startDrawing(e) {
                    this.isDrawing = true;
                    this.isEmpty = false;
                    const coords = this.getCoords(e);
                    this.lastX = coords.x;
                    this.lastY = coords.y;
                    this.ctx.beginPath();
                    this.ctx.moveTo(this.lastX, this.lastY);
                },
                
                draw(e) {
                    if (!this.isDrawing) return;
                    const coords = this.getCoords(e);
                    this.ctx.lineTo(coords.x, coords.y);
                    this.ctx.stroke();
                    this.ctx.beginPath();
                    this.ctx.moveTo(coords.x, coords.y);
                    this.lastX = coords.x;
                    this.lastY = coords.y;
                },
                
                stopDrawing() {
                    this.isDrawing = false;
                },
                
                clear() {
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                    this.isEmpty = true;
                },
                
                save() {
                    if (this.isEmpty) return;
                    const dataURL = this.canvas.toDataURL('image/png');
                    this.$wire.signatureData = dataURL;
                }
            };
        }
    </script>

    {{-- Google Font for typed signature --}}
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">

    {{-- PDF.js for mobile viewer --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Only run on mobile when pdf-viewer exists
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('pdf-viewer');
            if (!container || window.innerWidth >= 768) return;
            
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            
            const pdfUrl = '{{ route("documents.preview", $this->document ?? 0) }}';
            
            let pdfDoc = null;
            let scale = 1;
            const containerWidth = container.clientWidth;
            
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
                pdfDoc = pdf;
                
                // Render all pages
                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    pdf.getPage(pageNum).then(function(page) {
                        const viewport = page.getViewport({ scale: 1 });
                        scale = (containerWidth - 16) / viewport.width; // 16px padding
                        const scaledViewport = page.getViewport({ scale: scale });
                        
                        const wrapper = document.createElement('div');
                        wrapper.className = 'pdf-page-wrapper';
                        wrapper.style.cssText = 'margin: 8px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);';
                        
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = scaledViewport.height;
                        canvas.width = scaledViewport.width;
                        canvas.style.display = 'block';
                        
                        wrapper.appendChild(canvas);
                        container.appendChild(wrapper);
                        
                        page.render({
                            canvasContext: context,
                            viewport: scaledViewport
                        });
                    });
                }
            }).catch(function(error) {
                // Fallback to iframe if PDF.js fails
                console.warn('PDF.js failed, using iframe fallback:', error);
                container.innerHTML = '<iframe src="' + pdfUrl + '#toolbar=0&navpanes=0" style="width:100%;height:100%;border:none;"></iframe>';
            });
        });
    </script>

    <style>
        .safe-area-inset {
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        
        #pdf-viewer {
            -webkit-overflow-scrolling: touch;
        }
        
        .pdf-page-wrapper {
            border-radius: 4px;
            overflow: hidden;
        }
    </style>
</div>
