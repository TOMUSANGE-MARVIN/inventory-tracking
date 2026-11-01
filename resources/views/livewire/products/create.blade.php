<div>
    <x-modal wire:model="createModal" name="createModal">
        <x-slot name="title">
            {{ __('New product') }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="create" class="space-y-5">
                @include('livewire.products.partials.fields', ['codeId' => 'product-code-create'])

                <p class="text-xs text-gray-500"><i class="fas fa-info-circle text-primary-600 mr-1"></i>{{ __('Stock is added under Receive new stock, where every unit gets its own label.') }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('createModal', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn-primary"><i class="fas fa-check"></i>{{ __('Save product') }}</button>
                </div>
            </form>
        </x-slot>
    </x-modal>
</div>
