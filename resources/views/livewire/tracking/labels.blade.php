<div>
    <x-page-container title="{{ __('Print labels') }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Print labels')]]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="panel p-4 space-y-3 self-start">
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('New deliveries get labels when you record them under Receive new stock. Use this page to reprint labels.') }}</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['units' => ['tag', __('Unit barcodes')], 'boxes' => ['box', __('Box QR')]] as $value => [$icon, $label])
                        <button type="button" wire:click="$set('what', '{{ $value }}')" @class([
                            'px-3 py-2 rounded-lg text-sm font-medium border-2',
                            'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-900/30 dark:text-primary-200' => $what === $value,
                            'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' => $what !== $value,
                        ])><i class="fas fa-{{ $icon }} mr-1"></i>{{ $label }}</button>
                    @endforeach
                </div>
                @if ($what === 'units')
                <div class="flex gap-2">
                    @foreach (['barcode' => __('Barcode'), 'qr' => __('QR code')] as $value => $label)
                        <button type="button" wire:click="$set('type', '{{ $value }}')" @class([
                            'flex-1 px-3 py-2 rounded-lg text-sm font-medium border-2',
                            'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-900/30 dark:text-primary-200' => $type === $value,
                            'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' => $type !== $value,
                        ])>{{ $label }}</button>
                    @endforeach
                </div>
                @endif
                <select wire:model.live="warehouseId" class="w-full form-field">
                    <option value="">{{ __('All locations') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                @if ($what === 'boxes')
                    <select wire:model.live="boxId" class="w-full form-field">
                        <option value="">{{ __('Choose a box…') }}</option>
                        @foreach ($boxes as $b)
                            <option value="{{ $b->id }}">{{ $b->code }} · {{ $b->status->label() }}</option>
                        @endforeach
                    </select>
                    @if ($box)
                        <button type="button" onclick="window.print()" class="btn-primary w-full"><i class="fas fa-print"></i>{{ __('Print box label') }}</button>
                    @endif
                @else
                <select wire:model.live="productId" class="w-full form-field">
                    <option value="">{{ __('Choose product…') }}</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @if ($items->isNotEmpty())
                    <button type="button" onclick="window.print()" class="btn-primary w-full">
                        <i class="fas fa-print"></i>{{ __('Print') }} {{ $items->count() }} {{ __('labels') }}
                    </button>
                @endif
                @endif
            </div>

            <div class="lg:col-span-2 panel p-4 bg-white">
                @if ($what === 'boxes')
                    @if ($box)
                        <div class="print-area"><x-tracking.box-label :box="$box" /></div>
                    @else
                        <p class="py-10 text-center text-sm text-gray-500">{{ __('Choose a box to preview its QR label.') }}</p>
                    @endif
                @elseif ($items->isNotEmpty())
                    <x-tracking.label-sheet :items="$items" :type="$type" />
                @else
                    <p class="py-10 text-center text-sm text-gray-500">{{ __('Choose a product to preview its unit labels.') }}</p>
                @endif
            </div>
        </div>
    </x-page-container>
</div>
