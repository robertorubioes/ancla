<div
    x-data="templateEditor({ pdfUrl: @js($this->pdfUrl) })"
    class="max-w-[1400px] mx-auto py-6 px-4"
>
    {{-- Cabecera --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $template->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ __('Version') }} {{ $version->version }} · {{ __('borrador') }}
                @if ($template->description)
                    · {{ $template->description }}
                @endif
            </p>
        </div>

        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            class="shrink-0 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="save">{{ __('Guardar plantilla') }}</span>
            <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
        </button>
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ $error }}
        </div>
    @endif

    @if ($success)
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ $success }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">

        {{-- Lienzo: paginas del PDF con las cajas encima --}}
        <div class="bg-gray-100 rounded-xl p-6 overflow-auto">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <p class="text-xs text-gray-500">
                    {{ __('Haz doble clic sobre la pagina para anadir un campo. Arrastra para moverlo, y la esquina inferior derecha para redimensionarlo.') }}
                </p>

                @if (count($pages) > 1)
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-gray-500">{{ __('Ir a la pagina') }}</span>
                        <select
                            @change="goToPage($event.target.value)"
                            class="rounded-md border-gray-300 text-xs py-1 pr-7"
                        >
                            @foreach ($pages as $page)
                                <option value="{{ $page['number'] }}">
                                    {{ $page['number'] }}@if ($fieldsPerPage[$page['number']] ?? false) · {{ $fieldsPerPage[$page['number']] }} {{ __('campos') }}@endif
                                </option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-400">{{ __('de') }} {{ count($pages) }}</span>
                    </div>
                @endif
            </div>

            <template x-if="loadError">
                <p class="text-sm text-red-700 py-6 text-center" x-text="loadError"></p>
            </template>

            @forelse ($pages as $page)
                <div
                    wire:key="tpl-page-{{ $page['number'] }}"
                    class="tpl-page relative mx-auto mb-8 bg-white shadow-sm ring-1 ring-gray-200"
                    data-page="{{ $page['number'] }}"
                    data-mm-width="{{ $page['width'] }}"
                    data-mm-height="{{ $page['height'] }}"
                    style="width: 100%; max-width: 760px; aspect-ratio: {{ $page['width'] }} / {{ $page['height'] }}"
                    @dblclick="addFieldAt($event, {{ $page['number'] }})"
                >
                    {{-- El canvas queda fuera del alcance de Livewire.
                         Lo pintado no vive en el HTML, asi que cada vez que
                         Livewire rehacia el DOM -al anadir un campo, por
                         ejemplo- las paginas se quedaban en blanco. --}}
                    <div wire:ignore class="absolute inset-0">
                        <canvas
                            id="tpl-canvas-{{ $page['number'] }}"
                            class="w-full h-full"
                        ></canvas>
                    </div>

                    <span
                        class="tpl-page-badge absolute -top-3 left-2 z-10 px-2 py-0.5 rounded-full bg-gray-800/80 text-white text-[11px] font-medium"
                    >{{ $page['number'] }}</span>

                    {{-- Cajas de esta pagina. En PORCENTAJE: asi no dependen
                         de la escala a la que pinte el navegador. --}}
                    @foreach ($fields as $index => $field)
                        @continue($field['page'] !== $page['number'])
                        <div
                            wire:key="tpl-field-{{ $index }}"
                            data-field-index="{{ $index }}"
                            style="
                                left: {{ 100 * $field['x'] / $page['width'] }}%;
                                top: {{ 100 * $field['y'] / $page['height'] }}%;
                                width: {{ 100 * $field['width'] / $page['width'] }}%;
                                height: {{ 100 * $field['height'] / $page['height'] }}%;
                            "
                            @pointerdown="startDrag($event, {{ $index }}, 'move')"
                            class="absolute border-2 rounded cursor-move flex items-center px-1 overflow-hidden
                                {{ $selectedField === $index
                                    ? 'border-blue-600 bg-blue-100/70'
                                    : 'border-blue-400 bg-blue-50/60 hover:bg-blue-100/60' }}"
                        >
                            <span class="text-[10px] font-medium text-blue-900 truncate pointer-events-none">
                                {{ $field['label'] }}
                            </span>

                            <span
                                @pointerdown.stop="startDrag($event, {{ $index }}, 'resize')"
                                class="absolute -right-1 -bottom-1 w-3 h-3 bg-blue-600 rounded-sm cursor-se-resize"
                            ></span>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="text-sm text-gray-500 py-12 text-center">
                    {{ __('No se pudieron leer las paginas del documento.') }}
                </p>
            @endforelse
        </div>

        {{-- Panel lateral --}}
        <div class="space-y-6">

            {{-- Propiedades del campo seleccionado --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Campo seleccionado') }}</h2>

                @if ($selectedField !== null && isset($fields[$selectedField]))
                    @php $sel = $selectedField; @endphp

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Clave') }}</label>
                            <input
                                type="text"
                                wire:model.blur="fields.{{ $sel }}.key"
                                class="w-full rounded-md border-gray-300 text-sm font-mono"
                            >
                            <p class="text-[11px] text-gray-500 mt-1">
                                {{ __('Se usa en el formulario y en el JSON de la API.') }}
                            </p>
                            @error("fields.{$sel}.key")
                                <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Etiqueta') }}</label>
                            <input
                                type="text"
                                wire:model.blur="fields.{{ $sel }}.label"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Tipo') }}</label>
                            <select
                                wire:model.live="fields.{{ $sel }}.type"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                                @foreach ($this->fieldTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="fields.{{ $sel }}.required" class="rounded border-gray-300">
                            {{ __('Obligatorio') }}
                        </label>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Cuerpo') }}</label>
                                <input type="number" min="6" max="40" wire:model.blur="fields.{{ $sel }}.font_size"
                                    class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Alineacion') }}</label>
                                <select wire:model="fields.{{ $sel }}.align" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="left">{{ __('Izquierda') }}</option>
                                    <option value="center">{{ __('Centro') }}</option>
                                    <option value="right">{{ __('Derecha') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-4 gap-2 text-[11px] text-gray-500 pt-1">
                            <span>X {{ $fields[$sel]['x'] }}</span>
                            <span>Y {{ $fields[$sel]['y'] }}</span>
                            <span>W {{ $fields[$sel]['width'] }}</span>
                            <span>H {{ $fields[$sel]['height'] }}</span>
                        </div>

                        <button
                            type="button"
                            wire:click="removeField({{ $sel }})"
                            class="w-full mt-2 px-3 py-2 rounded-md border border-red-200 text-red-700 text-sm hover:bg-red-50"
                        >
                            {{ __('Eliminar campo') }}
                        </button>
                    </div>
                @else
                    <p class="text-sm text-gray-500">
                        {{ __('Ninguno. Haz doble clic sobre el documento para anadir uno.') }}
                    </p>
                @endif
            </div>

            {{-- Roles de firmante --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('Firmantes previstos') }}</h2>
                    <button
                        type="button"
                        wire:click="addSignerRole"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                    >
                        + {{ __('Anadir') }}
                    </button>
                </div>

                @forelse ($signerRoles as $i => $role)
                    <div class="border border-gray-200 rounded-lg p-3 mb-2 space-y-2">
                        <input
                            type="text"
                            wire:model.blur="signerRoles.{{ $i }}.label"
                            placeholder="{{ __('Arrendatario') }}"
                            class="w-full rounded-md border-gray-300 text-sm"
                        >
                        <input
                            type="text"
                            wire:model.blur="signerRoles.{{ $i }}.role_key"
                            placeholder="arrendatario"
                            class="w-full rounded-md border-gray-300 text-xs font-mono"
                        >
                        @error("signerRoles.{$i}.role_key")
                            <p class="text-[11px] text-red-600">{{ $message }}</p>
                        @enderror
                        <button
                            type="button"
                            wire:click="removeSignerRole({{ $i }})"
                            class="text-xs text-red-600 hover:text-red-800"
                        >
                            {{ __('Eliminar') }}
                        </button>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">
                        {{ __('Ninguno todavia. Un contrato necesita al menos uno.') }}
                    </p>
                @endforelse
            </div>

            {{-- Resumen de campos --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">
                    {{ __('Campos') }} ({{ count($fields) }})
                </h2>

                @forelse ($fields as $i => $field)
                    <button
                        type="button"
                        wire:click="selectField({{ $i }})"
                        class="w-full text-left px-2 py-1.5 rounded text-sm flex items-center justify-between
                            {{ $selectedField === $i ? 'bg-blue-50 text-blue-900' : 'hover:bg-gray-50 text-gray-700' }}"
                    >
                        <span class="truncate">{{ $field['label'] }}</span>
                        <span class="text-[11px] text-gray-400 shrink-0 ml-2">
                            {{ __('pag.') }} {{ $field['page'] }}
                        </span>
                    </button>
                @empty
                    <p class="text-sm text-gray-500">{{ __('Ninguno todavia.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
