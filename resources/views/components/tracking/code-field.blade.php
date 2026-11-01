@props(['id', 'model' => 'form.code'])

{{-- Product barcode: type it, use a USB scanner, scan with the camera, or generate a catalogue code. --}}
<div x-data="stockScanner('{{ $id }}-reader', '{{ $model }}')" class="mt-1 space-y-2">
    <div class="relative">
        <i class="fas fa-barcode absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input id="{{ $id }}" type="text" wire:model.blur="{{ $model }}" autocomplete="off" required
            placeholder="{{ __('Scan or type the barcode') }}" class="form-field w-full pl-9 font-mono">
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" @click="start()" class="btn-primary px-3 py-1.5 text-xs">
            <i class="fas fa-camera"></i>{{ __('Scan with camera') }}
        </button>
        <button type="button" wire:click="generateCode" class="btn-secondary px-3 py-1.5 text-xs" title="{{ __('No barcode on the product? Generate a code') }}">
            <i class="fas fa-magic"></i>{{ __('Generate code') }}
        </button>
    </div>
    <p x-show="error" x-text="error" x-cloak class="text-xs text-red-600"></p>

    {{-- Full-size scanner sheet: the decoder reads at the viewfinder's on-screen size, so it must not be squeezed into the form column.
         Teleported to <body> so the modal's layout cannot trap or shrink it. --}}
    <template x-teleport="body">
        <div x-show="running" x-cloak class="fixed inset-0 z-[120] flex items-end sm:items-center justify-center bg-gray-950/70 p-0 sm:p-4" @keydown.escape.window="stop()">
            <div class="w-full sm:max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl p-4 space-y-3" @click.outside="stop()">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-barcode mr-2 text-primary-600"></i>{{ __('Scan the product barcode') }}</h3>
                    <button type="button" @click="stop()" class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><i class="fas fa-times"></i></button>
                </div>
                <div wire:ignore>
                    <div id="{{ $id }}-reader" class="w-full overflow-hidden rounded-xl bg-gray-900"></div>
                </div>
                <p class="text-xs text-gray-500">{{ __('Hold the barcode inside the box – it fills in automatically and this closes.') }}</p>
            </div>
        </div>
    </template>
</div>
