@props(['id' => 'reader'])

{{-- Camera + manual/USB-scanner input. The parent Livewire component must define scanned(string $code). --}}
<div x-data="stockScanner('{{ $id }}')" class="panel p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-qrcode mr-2 text-primary-600"></i>{{ __('Scan barcode / QR code') }}</h3>
        <button type="button" x-show="!running" @click="start()" class="btn-primary w-full sm:w-auto py-3 sm:py-2">
            <i class="fas fa-camera"></i>{{ __('Start camera') }}
        </button>
        <button type="button" x-show="running" x-cloak @click="stop()"
            class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-4 py-3 sm:py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
            <i class="fas fa-stop"></i>{{ __('Stop camera') }}
        </button>
    </div>

    <div wire:ignore>
        <div id="{{ $id }}" class="scan-viewfinder w-full max-w-md mx-auto overflow-hidden rounded-xl bg-gray-900"></div>
    </div>

    <p x-show="running" x-cloak class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Hold the label inside the box – it registers automatically.') }}
        <span x-show="lastResult">{{ __('Last scan') }}: <span class="font-mono font-semibold text-gray-800 dark:text-gray-200" x-text="lastResult"></span></span>
    </p>
    <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600"></p>

    <form @submit.prevent="submitManual()" class="flex gap-2">
        <input type="text" x-model="manualCode" autocomplete="off" autocapitalize="characters" enterkeyhint="go"
            placeholder="{{ __('Type or scan a code') }}" class="flex-1 min-w-0 form-field">
        <button type="submit" class="shrink-0 px-4 rounded-lg bg-gray-900 dark:bg-gray-700 text-white text-sm font-medium">{{ __('Add') }}</button>
    </form>
</div>
