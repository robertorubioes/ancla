<div class="max-w-3xl mx-auto py-6 px-4">
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

    <form wire:submit="generate" class="space-y-6">

        {{-- Campos de la plantilla --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Datos del documento') }}</h2>

            @forelse ($fields as $field)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ $field->label }}
                        @unless ($field->required)
                            <span class="text-gray-400 font-normal">({{ __('opcional') }})</span>
                        @endunless
                    </label>

                    @switch ($field->type->value)
                        @case ('textarea')
                            <textarea rows="3" wire:model.blur="values.{{ $field->key }}"
                                class="w-full rounded-md border-gray-300 text-sm"></textarea>
                            @break

                        @case ('select')
                            <select wire:model.blur="values.{{ $field->key }}"
                                class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('-- Elige --') }}</option>
                                @foreach ($field->optionMap() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @break

                        @case ('checkbox')
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model.blur="values.{{ $field->key }}"
                                    class="rounded border-gray-300">
                                {{ $field->help_text ?: __('Si') }}
                            </label>
                            @break

                        @case ('number')
                            <input type="number" step="any" wire:model.blur="values.{{ $field->key }}"
                                class="w-full rounded-md border-gray-300 text-sm">
                            @break

                        @case ('date')
                            <input type="date" wire:model.blur="values.{{ $field->key }}"
                                class="w-full rounded-md border-gray-300 text-sm">
                            @break

                        @default
                            <input type="text" wire:model.blur="values.{{ $field->key }}"
                                class="w-full rounded-md border-gray-300 text-sm">
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
            <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Firmantes') }}</h2>

            @foreach ($roles as $role)
                <div class="mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">
                        {{ $role->label }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <input type="text" placeholder="{{ __('Nombre completo') }}"
                                wire:model.blur="signers.{{ $role->role_key }}.name"
                                class="w-full rounded-md border-gray-300 text-sm">
                            @error("signers.{$role->role_key}.name")
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <input type="email" placeholder="{{ __('Correo') }}"
                                wire:model.blur="signers.{{ $role->role_key }}.email"
                                class="w-full rounded-md border-gray-300 text-sm">
                            @error("signers.{$role->role_key}.email")
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
                    <select wire:model="signatureOrder" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="parallel">{{ __('Todos a la vez') }}</option>
                        <option value="sequential">{{ __('Uno detras de otro') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fecha limite') }}</label>
                    <input type="date" wire:model.blur="deadlineAt"
                        class="w-full rounded-md border-gray-300 text-sm">
                    @error('deadlineAt')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Mensaje para los firmantes') }}</label>
                <textarea rows="2" wire:model.blur="customMessage"
                    class="w-full rounded-md border-gray-300 text-sm"></textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="sendNow" class="rounded border-gray-300">
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
</div>
