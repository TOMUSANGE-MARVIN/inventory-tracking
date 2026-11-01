<div class="space-y-5">
    <x-theme.breadcrumb :title="__('Edit product')" :parent="route('products.index')" :parentName="__('Products')" :children="url()->current()" :childrenName="$product->name" />

    <form wire:submit="update" class="panel p-4 sm:p-6 max-w-3xl space-y-5">
        @include('livewire.products.partials.fields', ['codeId' => 'product-code-edit'])

        <div class="flex justify-end gap-2">
            <a href="{{ route('products.index') }}" wire:navigate class="btn-secondary">{{ __('Back to products') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary"><i class="fas fa-check"></i>{{ __('Save changes') }}</button>
        </div>
    </form>
</div>
