<div>
    <x-page-container title="{{ __('Dispatch to branch') }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Transfers'), 'url' => route('tracking.transfers')], ['label' => __('Dispatch')]]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="panel p-4 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-gray-700 dark:text-gray-300">{{ __('From') }}</span>
                        <select wire:model.live="fromWarehouseId" class="mt-1 w-full form-field">
                            @foreach ($fromWarehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-700 dark:text-gray-300">{{ __('To') }}</span>
                        <select wire:model.live="toWarehouseId" class="mt-1 w-full form-field">
                            <option value="">{{ __('Select location…') }}</option>
                            @foreach ($warehouses as $w)
                                @if ($w->id != $fromWarehouseId)
                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                </div>

                <x-tracking.scanner id="dispatch-reader" />
                <x-tracking.flash :flash="$flash" />
            </div>

            <div class="panel p-4 space-y-3">
                @php($totalUnits = array_sum(array_column($boxes, 'units')) + count($items))
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('To dispatch') }}
                    <span class="text-sm font-normal text-gray-500">({{ count($boxes) }} {{ __('boxes') }} · {{ $totalUnits }} {{ __('units') }})</span></h3>

                {{-- Whole boxes --}}
                @foreach ($boxes as $id => $box)
                    <div wire:key="box-{{ $id }}" class="flex items-start gap-3 rounded-xl border border-gray-200 dark:border-gray-800 p-3">
                        <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center bg-primary-50 dark:bg-primary-900/30 text-primary-600"><i class="fas fa-box"></i></span>
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $box['code'] }}</span>
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $box['units'] }} {{ __('units') }}</span>
                            </span>
                            <span class="block text-xs text-gray-500 line-clamp-2">{{ $box['summary'] }}</span>
                        </span>
                        <button type="button" wire:click="removeBox({{ $id }})" class="text-gray-400 hover:text-red-600" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                    </div>
                @endforeach

                {{-- Loose units --}}
                @foreach ($grouped as $product => $group)
                    <div wire:key="grp-{{ md5($product) }}" class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-3">
                        <div class="flex justify-between text-sm font-medium text-gray-900 dark:text-white">
                            <span><i class="fas fa-tag text-xs text-gray-400 mr-1"></i>{{ $product }}</span><span>× {{ $group->count() }}</span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($group as $item)
                                <span wire:key="it-{{ $item['id'] }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-xs font-mono text-gray-700 dark:text-gray-200">
                                    {{ $item['code'] }}
                                    <button type="button" wire:click="remove({{ $item['id'] }})" class="text-gray-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if (empty($boxes) && empty($items))
                    <p class="py-6 text-center text-sm text-gray-500">{{ __('Scan a box QR to send the whole box, or a unit barcode to send it on its own.') }}</p>
                @endif

                <textarea wire:model="note" rows="2" placeholder="{{ __('Note (optional)') }}" class="w-full form-field"></textarea>

                <button type="button" wire:click="dispatchTransfer" wire:loading.attr="disabled"
                    @disabled((empty($items) && empty($boxes)) || ! $toWarehouseId)
                    class="btn-primary w-full">
                    <i class="fas fa-truck"></i>{{ __('Dispatch – mark as in transit') }}
                </button>
            </div>
        </div>
    </x-page-container>
</div>
