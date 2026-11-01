<div>
    <x-page-container title="{{ __('Receive new stock') }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Receive new stock')]]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                {{-- Boxed or loose --}}
                <div class="panel p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ([
                            'box' => ['box', __('Boxed delivery'), __('QR on the box, a barcode on every unit inside')],
                            'loose' => ['oil-can', __('Loose items'), __('Jerrycans, drums… a barcode on each')],
                        ] as $value => [$icon, $label, $hint])
                            <button type="button" wire:click="$set('mode', '{{ $value }}')" @class([
                                'text-left rounded-xl border-2 p-3 transition',
                                'border-primary-600 bg-primary-50 dark:bg-primary-900/30' => $mode === $value,
                                'border-gray-200 dark:border-gray-700' => $mode !== $value,
                            ])>
                                <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><i class="fas fa-{{ $icon }} text-primary-600"></i>{{ $label }}</span>
                                <span class="block mt-0.5 text-xs text-gray-500">{{ $hint }}</span>
                            </button>
                        @endforeach
                    </div>
                    <ol class="hidden sm:block text-xs text-gray-600 dark:text-gray-300 space-y-1 list-decimal list-inside">
                        @if ($mode === 'box')
                            <li>{{ __('Open the box and scan the barcode on each product, then set how many.') }}</li>
                            <li>{{ __('Save: the box gets a QR label and every unit its own barcode.') }}</li>
                            <li>{{ __('Stick the labels on, put the units back and close the box.') }}</li>
                        @else
                            <li>{{ __('Scan the barcode on each item, then set how many.') }}</li>
                            <li>{{ __('Save and stick a barcode label on every item.') }}</li>
                        @endif
                    </ol>
                </div>

                <x-tracking.scanner id="arrival-reader" />
                <x-tracking.flash :flash="$flash" />
            </div>

            <div class="panel p-4 space-y-3">

                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $mode === 'box' ? __('What is inside this one box') : __('Items') }}</span>
                    <span class="text-xs text-gray-500">{{ $mode === 'box' ? __('Quantity = units inside the box') : __('Quantity = number of items') }}</span>
                </div>

                {{-- Scanning adds the product; staff only adjust the quantity. Latest scan on top. --}}
                <ul class="divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-200 dark:border-gray-800">
                    @forelse (array_reverse($lines, true) as $pid => $n)
                        <li wire:key="line-{{ $pid }}" @class(['flex items-center gap-2 px-3 py-2.5', 'bg-primary-50/60 dark:bg-primary-900/20' => $pid === $lastProductId])>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white truncate">{{ $lineProducts[$pid]?->name }}</span>
                                <span class="block font-mono text-[11px] text-gray-500 truncate">{{ $lineProducts[$pid]?->code }}</span>
                            </span>
                            <span class="flex flex-col items-center shrink-0">
                            <span class="flex items-center rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <button type="button" wire:click="setLine('{{ $pid }}', {{ $n - 1 }})" class="w-9 h-9 grid place-items-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="{{ __('One less') }}"><i class="fas fa-minus text-xs"></i></button>
                                <input type="number" min="0" max="500" inputmode="numeric" value="{{ $n }}" wire:change="setLine('{{ $pid }}', $event.target.value)" aria-label="{{ __('Quantity') }}"
                                    class="w-14 h-9 text-center text-sm font-semibold bg-transparent border-x border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" wire:click="setLine('{{ $pid }}', {{ $n + 1 }})" class="w-9 h-9 grid place-items-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="{{ __('One more') }}"><i class="fas fa-plus text-xs"></i></button>
                            </span>
                            <span class="mt-0.5 text-[11px] text-gray-500">{{ $mode === 'box' ? trans_choice('unit in box|units in box', $n) : trans_choice('item|items', $n) }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="px-3 py-6 text-center text-sm text-gray-500">
                            <i class="fas fa-barcode text-2xl text-gray-300 dark:text-gray-600 block mb-2"></i>
                            {{ $mode === 'box' ? __('Scan the barcode on each product inside the box.') : __('Scan the barcode on each item.') }}
                        </li>
                    @endforelse
                </ul>

                {{-- Rare case: a product with no barcode on it. --}}
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                        <i class="fas fa-search mr-1"></i>{{ __('No barcode on it? Pick the product') }}
                    </button>
                    <div x-show="open" x-cloak class="mt-2 flex gap-2">
                        <select wire:model="productId" class="form-field flex-1 min-w-0">
                            <option value="">{{ __('Choose product…') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addLine" class="btn-secondary px-3 shrink-0"><i class="fas fa-plus"></i>{{ __('Add') }}</button>
                    </div>
                </div>

                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ __('Received at') }}</span>
                    <select wire:model="warehouseId" class="mt-1 w-full form-field">
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="text" wire:model="batch" placeholder="{{ __('Batch / lot (optional)') }}" class="form-field">
                    <input type="text" wire:model="note" placeholder="{{ __('Supplier / delivery note') }}" class="form-field">
                </div>

                @php($units = array_sum($lines))
                @if ($lines)
                    <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 p-3 text-sm text-primary-900 dark:text-primary-100">
                        @if ($mode === 'box')
                            {!! __('You are recording <strong>1 box</strong> containing <strong>:n</strong>. The box gets one QR label and each unit its own barcode.', ['n' => e(trans_choice(':n unit|:n units', $units, ['n' => $units]))]) !!}
                            <span class="block text-xs mt-1 opacity-80">{{ __('Several boxes? Save each box on its own so each gets its own QR label.') }}</span>
                        @else
                            {!! __('You are recording <strong>:n</strong>, each with its own barcode.', ['n' => e(trans_choice(':n item|:n items', $units, ['n' => $units]))]) !!}
                        @endif
                    </div>
                @endif

                <button type="button" wire:click="save" wire:loading.attr="disabled" @disabled(empty($lines))
                    @if ($mode === 'box' && $units === 1) wire:confirm="{{ __('This box has only 1 unit inside. The quantity is the number of units in the box, not the number of boxes. Save anyway?') }}" @endif
                    class="btn-primary w-full">
                    <i class="fas fa-{{ $mode === 'box' ? 'box' : 'tags' }}"></i>
                    @if ($mode === 'box')
                        {{ $lines ? trans_choice('Save 1 box with :n unit & print labels|Save 1 box with :n units & print labels', $units, ['n' => $units]) : __('Save box & print labels') }}
                    @else
                        {{ $lines ? trans_choice('Save :n item & print labels|Save :n items & print labels', $units, ['n' => $units]) : __('Save items & print labels') }}
                    @endif
                </button>
            </div>
        </div>

        @if ($created->isNotEmpty())
            <div class="mt-4 panel p-4 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        {{ $createdBox ? __('Labels for box :code', ['code' => $createdBox->code]) : __('Labels for this delivery') }} · {{ $created->count() }} {{ __('units') }}
                    </h3>
                    <button type="button" onclick="window.print()" class="btn-primary py-1.5"><i class="fas fa-print"></i>{{ __('Print labels') }}</button>
                </div>
                <div class="print-area space-y-4">
                    @if ($createdBox)
                        <x-tracking.box-label :box="$createdBox" />
                    @endif
                    <x-tracking.label-sheet :items="$created" type="barcode" :standalone="false" />
                </div>
                <div x-data="{ open: false }" class="border-t border-gray-100 dark:border-gray-800 pt-3">
                    <button type="button" x-show="!open" @click="open = true" class="text-sm text-red-600 hover:underline"><i class="fas fa-undo mr-1"></i>{{ __('Entered by mistake? Undo this delivery') }}</button>
                    <div x-show="open" x-cloak class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 space-y-2">
                        <p class="text-sm text-red-900 dark:text-red-200">{{ __('Removes this delivery and its :n units from stock. Only possible before any unit is moved or used. The undo is logged with your name and reason.', ['n' => $created->count()]) }}</p>
                        <input type="text" wire:model="undoReason" placeholder="{{ __('Reason, e.g. wrong quantity entered') }}" class="form-field w-full">
                        @error('undoReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="flex gap-2">
                            <button type="button" wire:click="undoLast" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700"><i class="fas fa-undo"></i>{{ __('Undo delivery') }}</button>
                            <button type="button" @click="open = false" class="btn-secondary py-2 text-sm">{{ __('Keep it') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-page-container>
</div>
