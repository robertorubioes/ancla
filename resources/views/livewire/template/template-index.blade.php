<div class="max-w-5xl mx-auto py-6 px-4">

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Plantillas') }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ __('Documentos con campos variables, para procesos que se repiten.') }}
            </p>
        </div>
        <button type="button" wire:click="openCreate"
            class="shrink-0 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            {{ __('Nueva plantilla') }}
        </button>
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $error }}</div>
    @endif
    @if ($success)
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ $success }}</div>
    @endif

    <div class="flex gap-3 mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar...') }}"
            class="flex-1 rounded-md border border-gray-300 text-sm">
        <select wire:model.live="statusFilter" class="rounded-md border border-gray-300 text-sm">
            <option value="">{{ __('Todas') }}</option>
            <option value="draft">{{ __('En borrador') }}</option>
            <option value="active">{{ __('Habilitadas') }}</option>
            <option value="archived">{{ __('Retiradas') }}</option>
        </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @forelse ($items as $item)
            <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-gray-100 last:border-0">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-900 truncate">{{ $item->name }}</p>

                        @if ($item->status === \App\Models\DocumentTemplate::STATUS_ACTIVE)
                            <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-800">
                                {{ __('habilitada') }}
                            </span>
                        @elseif ($item->status === \App\Models\DocumentTemplate::STATUS_ARCHIVED)
                            <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                {{ __('retirada') }}
                            </span>
                        @else
                            <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                                {{ __('sin habilitar') }}
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-gray-500 mt-0.5">
                        @if ($item->currentVersion)
                            {{ __('Version') }} {{ $item->currentVersion->version }} ·
                        @endif
                        {{ trans_choice(':count version|:count versiones', $item->versions_count, ['count' => $item->versions_count]) }}
                        @if ($item->creator) · {{ $item->creator->name }} @endif
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if ($item->isUsable())
                        <a href="{{ route('templates.fill', ['template' => $item->uuid]) }}" wire:navigate
                           class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700">
                            {{ __('Usar') }}
                        </a>
                    @endif

                    @if ($item->status !== \App\Models\DocumentTemplate::STATUS_ARCHIVED)
                        <button type="button" wire:click="edit('{{ $item->uuid }}')"
                            class="px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                            {{ __('Editar') }}
                        </button>
                    @endif

                    @if ($item->status === \App\Models\DocumentTemplate::STATUS_DRAFT)
                        <button type="button" wire:click="enable('{{ $item->uuid }}')"
                            class="px-3 py-1.5 rounded-md bg-green-600 text-white text-sm hover:bg-green-700">
                            {{ __('Habilitar') }}
                        </button>
                    @endif

                    @if ($item->status === \App\Models\DocumentTemplate::STATUS_ACTIVE)
                        <button type="button" wire:click="archive('{{ $item->uuid }}')"
                            class="px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-50">
                            {{ __('Retirar') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center">
                <p class="text-sm text-gray-500">{{ __('Ninguna plantilla todavia.') }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    {{ __('Una plantilla parte de un documento que ya hayas subido.') }}
                </p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>

    {{-- Convertir un documento en plantilla --}}
    @if ($showCreate)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.5)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 relative z-10">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('Nueva plantilla') }}</h2>
                <p class="text-sm text-gray-500 mb-4">
                    {{ __('Parte de un documento ya subido. Despues podras colocar sus campos variables.') }}
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Documento') }}</label>

                        @if ($sourceDocumentId && ! $uploading)
                            <div class="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-3 py-2">
                                <span class="text-sm text-green-800 truncate">{{ __('Documento listo') }}</span>
                                <button type="button" wire:click="$set('sourceDocumentId', null)"
                                    class="text-xs text-green-700 hover:text-green-900 shrink-0 ml-2">
                                    {{ __('Cambiar') }}
                                </button>
                            </div>
                        @else
                            <label class="flex flex-col items-center justify-center px-4 py-8 rounded-lg border-2 border-dashed border-gray-300 cursor-pointer hover:border-gray-400 hover:bg-gray-50">
                                <input type="file" wire:model="uploadedFile" accept="application/pdf" class="hidden">
                                <span wire:loading.remove wire:target="uploadedFile" class="text-sm text-gray-600">
                                    {{ __('Haz clic para elegir un PDF') }}
                                </span>
                                <span wire:loading.remove wire:target="uploadedFile" class="text-xs text-gray-400 mt-1">
                                    {{ __('Maximo 50 MB') }}
                                </span>
                                <span wire:loading wire:target="uploadedFile" class="text-sm text-gray-600">
                                    {{ __('Subiendo...') }}
                                </span>
                            </label>
                        @endif

                        @error('uploadedFile')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                        @error('sourceDocumentId')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nombre') }}</label>
                        <input type="text" wire:model="newName" class="w-full rounded-md border border-gray-300 text-sm">
                        @error('newName')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Descripcion') }} <span class="text-gray-400 font-normal">({{ __('opcional') }})</span>
                        </label>
                        <textarea rows="2" wire:model="newDescription"
                            class="w-full rounded-md border border-gray-300 text-sm"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeCreate"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="button" wire:click="createFromDocument" wire:loading.attr="disabled"
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                        {{ __('Crear y colocar campos') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
