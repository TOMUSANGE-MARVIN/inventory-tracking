
{{-- The basics a product needs. Stock is added later through "Receive new stock". --}}
@php($units = \App\Livewire\Forms\ProductForm::UNITS)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="{{ $codeId }}-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Product name') }} <span class="text-red-500">*</span></label>
        <input id="{{ $codeId }}-name" type="text" wire:model="form.name" placeholder="{{ __('e.g. Professional Shampoo 5L') }}" class="form-field w-full mt-1">
        @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $codeId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Barcode') }} <span class="text-red-500">*</span></label>
        <x-tracking.code-field id="{{ $codeId }}" />
        @error('form.code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Category') }} <span class="text-red-500">*</span></label>
        <select wire:model="form.category_id" class="form-field w-full mt-1">
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($this->categories as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        @error('form.category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Unit') }}</label>
        <select wire:model="form.unit" class="form-field w-full mt-1">
            @foreach ($units as $unit)
                <option value="{{ $unit }}">{{ ucfirst($unit) }}</option>
            @endforeach
        </select>
    </div>

    @foreach ([['price', __('Selling price'), true], ['cost', __('Cost price'), false]] as [$field, $label, $required])
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
            <div class="relative mt-1">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400">UGX</span>
                <input type="number" min="0" step="any" inputmode="numeric" wire:model="form.{{ $field }}" class="form-field w-full pl-12">
            </div>
            @error('form.' . $field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Re-order level') }}</label>
        <div class="flex items-center gap-3 mt-1">
            <input type="number" min="0" wire:model="form.stock_alert" class="form-field w-28">
            <span class="text-xs text-gray-500">{{ __('Warn when a location has this many units or fewer. 0 = no warning.') }}</span>
        </div>
        @error('form.stock_alert') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <label class="sm:col-span-2 flex items-start gap-3 rounded-xl border border-gray-200 dark:border-gray-700 p-3 cursor-pointer hover:border-primary-400">
        <input type="checkbox" wire:model="form.is_refillable" class="mt-0.5 h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
        <span>
            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100"><i class="fas fa-redo-alt text-primary-600 mr-1"></i>{{ __('Is refillable') }}</span>
            <span class="block text-xs text-gray-500">{{ __('When an item is used up it goes to “Needs refill” instead of being thrown away, then back into stock with the same barcode after refilling (e.g. jerrycans, refill bottles).') }}</span>
        </span>
    </label>
</div>
