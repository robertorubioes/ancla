<div class="max-w-[1400px] mx-auto py-6 px-4">
    <div class="mb-6">
        <a href="{{ route('templates.index') }}" wire:navigate
           class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Plantillas') }}</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">{{ $template->name }}</h1>
        @if ($template->description)
            <p class="text-sm text-gray-500 mt-1">{{ $template->description }}</p>
        @endif
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ $error }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,720px)] gap-6 items-start">

    <form wire:submit="generate" class="space-y-6">

        {{-- Campos de la plantilla --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Datos del documento') }}</h2>

            @forelse ($fields as $field)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ $field->label }}
                        @if ($field->required)
                            <span class="text-red-500">*</span>
                        @else
                            <span class="text-gray-400 font-normal text-xs">{{ __('(opcional)') }}</span>
                        @endif
                    </label>

                    @switch ($field->type->value)
                        @case ('textarea')
                            <textarea rows="3" wire:model.live.debounce.500ms="values.{{ $field->key }}"
                                class="w-full rounded-md border border-gray-300 text-sm"></textarea>
                            @break

                        @case ('select')
                            <select wire:model.live.debounce.500ms="values.{{ $field->key }}"
                                class="w-full rounded-md border border-gray-300 text-sm">
                                <option value="">{{ __('-- Elige --') }}</option>
                                @foreach ($field->optionMap() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @break

                        @case ('checkbox')
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model.live.debounce.500ms="values.{{ $field->key }}"
                                    class="rounded border border-gray-300">
                                {{ $field->help_text ?: __('Si') }}
                            </label>
                            @break

                        @case ('number')
                            <input type="number" step="any" wire:model.live.debounce.500ms="values.{{ $field->key }}"
                                class="w-full rounded-md border border-gray-300 text-sm">
                            @break

                        @case ('date')
                            <input type="date" wire:model.live.debounce.500ms="values.{{ $field->key }}"
                                class="w-full rounded-md border border-gray-300 text-sm">
                            @break

                        @default
                            <input type="text" wire:model.live.debounce.500ms="values.{{ $field->key }}"
                                class="w-full rounded-md border border-gray-300 text-sm">
                    @endswitch

                    @if ($field->help_text && $field->type->value !== 'checkbox')
                        <p class="text-xs text-gray-500 mt-1">{{ $field->help_text }}</p>
                    @endif

                    @error("values.{$field->key}")
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('Esta plantilla no tiene campos variables.') }}</p>
            @endforelse
        </div>

        {{-- Firmantes --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('Firmantes') }}</h2>
                @unless ($fixedRoles)
                    <button type="button" wire:click="addSigner"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                        + {{ __('Anadir firmante') }}
                    </button>
                @endunless
            </div>

            @if ($fixedRoles)
                <p class="text-xs text-gray-500 mb-4">
                    {{ __('Esta plantilla tiene los firmantes previstos. Indica quien ocupa cada papel.') }}
                </p>
            @endif

            @foreach ($signers as $i => $signer)
                <div class="mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            {{ ($signer['label'] ?? '') ?: __('Firmante') . ' ' . ($i + 1) }}
                        </p>
                        @if (! $fixedRoles && count($signers) > 1)
                            <button type="button" wire:click="removeSigner({{ $i }})"
                                class="text-xs text-red-600 hover:text-red-800">{{ __('Quitar') }}</button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <input type="text" placeholder="{{ __('Nombre completo') }}"
                                wire:model.live.debounce.500ms="signers.{{ $i }}.name"
                                class="w-full rounded-md border border-gray-300 text-sm">
                            @error("signers.{$i}.name")
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <input type="email" placeholder="{{ __('Correo') }}"
                                wire:model.live.debounce.500ms="signers.{{ $i }}.email"
                                class="w-full rounded-md border border-gray-300 text-sm">
                            @error("signers.{$i}.email")
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Opciones del proceso --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Envio') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Orden de firma') }}</label>
                    <select wire:model="signatureOrder" class="w-full rounded-md border border-gray-300 text-sm">
                        <option value="parallel">{{ __('Todos a la vez') }}</option>
                        <option value="sequential">{{ __('Uno detras de otro') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fecha limite') }}</label>
                    <input type="date" wire:model.blur="deadlineAt"
                        class="w-full rounded-md border border-gray-300 text-sm">
                    @error('deadlineAt')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Mensaje para los firmantes') }}</label>
                <textarea rows="2" wire:model.blur="customMessage"
                    class="w-full rounded-md border border-gray-300 text-sm"></textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="sendNow" class="rounded border border-gray-300">
                {{ __('Enviar en cuanto se genere') }}
            </label>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('templates.index') }}" wire:navigate
               class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                {{ __('Cancelar') }}
            </a>
            <button type="submit" wire:loading.attr="disabled"
                class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">{{ __('Generar y enviar') }}</span>
                <span wire:loading wire:target="generate">{{ __('Generando...') }}</span>
            </button>
        </div>
    </form>

    {{-- Vista previa --}}
    <div class="xl:sticky xl:top-6">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('Documento') }}</h2>
                <span wire:loading wire:target="preview" class="text-xs text-gray-500">
                    {{ __('Actualizando...') }}
                </span>
            </div>

            @if ($previewError)
                <div class="m-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                    {{ $previewError }}
                </div>
            @else
                {{-- Dos iframes que se turnan.
                     Recargar uno solo dejaba el panel en negro mientras el PDF
                     se abria, y con un documento grande eso es un parpadeo en
                     cada tecla. Se carga el nuevo por detras y se cambia
                     cuando ya esta pintado. --}}
                <div
                    x-data="{
                        first: true,
                        srcA: '',
                        srcB: '',
                        loading: false,
                        show(key) {
                            if (! key) { return; }

                            const url = @js(route('templates.preview', ['key' => '__KEY__']))
                                .replace('__KEY__', key) + '#toolbar=0&navpanes=0&statusbar=0&view=FitH';

                            if (this.srcA === url || this.srcB === url) { return; }

                            this.loading = true;
                            if (this.first) { this.srcB = url; } else { this.srcA = url; }
                        },
                        ready() {
                            if (! this.loading) { return; }
                            this.first = ! this.first;
                            this.loading = false;
                        },
                    }"
                    x-init="
                        show($wire.previewKey);
                        $wire.$watch('previewKey', (key) => show(key));
                    "
                    class="relative"
                    style="height: calc(100vh - 220px)"
                >
                    <iframe x-show="first" :src="srcA" @load="ready()"
                        class="absolute inset-0 w-full h-full" style="border: 0"
                        title="{{ __('Vista previa del documento') }}"></iframe>

                    <iframe x-show="! first" :src="srcB" @load="ready()"
                        class="absolute inset-0 w-full h-full" style="border: 0"
                        title="{{ __('Vista previa del documento') }}"></iframe>

                    <div x-show="! srcA && ! srcB" class="absolute inset-0 flex items-center justify-center">
                        <p class="text-sm text-gray-500">{{ __('Preparando el documento...') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    </div>
</div>
