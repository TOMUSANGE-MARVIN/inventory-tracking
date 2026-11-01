<div>
    <x-page-container title="{{ __('Receive') }} {{ $transfer->reference }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Transfers'), 'url' => route('tracking.transfers')], ['label' => $transfer->reference]]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="panel p-4 text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <div><span class="text-gray-500">{{ __('Route') }}:</span> <b>{{ $transfer->fromWarehouse?->name }}</b> → <b>{{ $transfer->toWarehouse?->name }}</b></div>
                    <div><span class="text-gray-500">{{ __('Dispatched') }}:</span> {{ $transfer->dispatched_at?->format('d M Y H:i') }} {{ __('by') }} {{ $transfer->dispatcher?->name }}</div>
                    <div><span class="text-gray-500">{{ __('Status') }}:</span>
                        <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $transfer->status->color() }}">{{ $transfer->status->label() }}</span>
                    </div>
                    @if ($transfer->received_at)
                        <div><span class="text-gray-500">{{ __('Received') }}:</span> {{ $transfer->received_at->format('d M Y H:i') }} {{ __('by') }} {{ $transfer->receiver?->name }}</div>
                    @endif
                    @if ($transfer->note)
                        <div class="whitespace-pre-line"><span class="text-gray-500">{{ __('Note') }}:</span> {{ $transfer->note }}</div>
                    @endif
                </div>

                @if ($open)
                    <x-tracking.scanner id="receive-reader" />
                @elseif ($awaitingBranch)
                    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 text-sm text-amber-900 dark:text-amber-200">
                        <i class="fas fa-hourglass-half mr-1"></i>
                        {{ __('Waiting for :branch to scan this delivery in. Only the receiving branch can confirm what arrived.', ['branch' => $transfer->toWarehouse?->name]) }}
                    </div>
                @endif
                <x-tracking.flash :flash="$flash" />
            </div>

            <div class="panel p-4 space-y-3">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('Items on this transfer') }}
                    <span class="text-sm font-normal text-gray-500">({{ count($scannedIds) }} / {{ $transfer->total_qty }} {{ __('received') }})</span></h3>

                {{-- Boxes: scan the box when it arrives, then every unit inside it. --}}
                @foreach ($boxes as $boxCode => $units)
                    @php($got = $units->whereIn('id', $scannedIds)->count())
                    <div wire:key="rb-{{ $boxCode }}" class="rounded-xl border border-gray-200 dark:border-gray-800 p-3">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white"><i class="fas fa-box text-primary-600"></i><span class="font-mono">{{ $boxCode }}</span>
                                @if ($open)
                                    @if (in_array($boxCode, $scannedBoxes, true))
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-800"><i class="fas fa-check"></i> {{ __('box scanned') }}</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">{{ __('scan the box first') }}</span>
                                    @endif
                                @endif
                            </span>
                            <span @class(['font-medium', 'text-green-600' => $got === $units->count(), 'text-red-600' => ! $open && $got < $units->count(), 'text-gray-500' => $open && $got < $units->count()])>{{ $got }} / {{ $units->count() }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($units as $item)
                                @php($in = in_array($item->id, $scannedIds, true))
                                <span wire:key="rbi-{{ $item->id }}" title="{{ $item->product?->name }}" @class([
                                    'px-2 py-0.5 rounded text-xs font-mono',
                                    'bg-green-100 text-green-800' => $in,
                                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300' => ! $in && $open,
                                    'bg-red-100 text-red-800 line-through' => ! $in && ! $open,
                                ])>{!! $in ? '<i class="fas fa-check mr-1"></i>' : '' !!}{{ $item->code }}</span>
                            @endforeach
                        </div>
                        <div class="mt-1 text-[11px] text-gray-500">{{ $units->groupBy('product.name')->map(fn ($u, $name) => $u->count() . ' × ' . $name)->join(', ') }}</div>
                    </div>
                @endforeach

                @foreach ($groups as $product => $items)
                    @php($got = $items->whereIn('id', $scannedIds)->count())
                    <div wire:key="rg-{{ md5($product) }}" class="border-b dark:border-gray-700 pb-2">
                        <div class="flex justify-between text-sm font-medium text-gray-900 dark:text-gray-100">
                            <span>{{ $product }}</span>
                            <span @class(['text-green-600' => $got === $items->count(), 'text-red-600' => ! $open && $got < $items->count()])>{{ $got }} / {{ $items->count() }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($items as $item)
                                @php($in = in_array($item->id, $scannedIds, true))
                                <span wire:key="ri-{{ $item->id }}" @class([
                                    'px-2 py-0.5 rounded text-xs font-mono',
                                    'bg-green-100 text-green-800' => $in,
                                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300' => ! $in && $open,
                                    'bg-red-100 text-red-800 line-through' => ! $in && ! $open,
                                ])>{!! $in ? '<i class="fas fa-check mr-1"></i>' : '' !!}{{ $item->code }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($open)
                    <textarea wire:model="note" rows="2" placeholder="{{ __('Note, e.g. reason for a missing item (optional)') }}" class="w-full form-field"></textarea>
                    <button type="button" wire:click="confirm" wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg bg-[#1baf7a] text-white font-semibold hover:bg-[#169a6b]">
                        <i class="fas fa-check"></i>{{ __('Confirm receipt') }}
                        @if (count($scannedIds) < $transfer->total_qty)
                            – {{ $transfer->total_qty - count($scannedIds) }} {{ __('will be marked missing') }}
                        @endif
                    </button>
                @endif

                @if ($canCancel)
                    <div x-data="{ open: false }" class="pt-2">
                        <button type="button" x-show="!open" @click="open = true" class="text-sm text-red-600 hover:underline"><i class="fas fa-ban mr-1"></i>{{ __('Cancel this dispatch') }}</button>
                        <div x-show="open" x-cloak class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 space-y-2">
                            <p class="text-sm text-red-900 dark:text-red-200">{{ __('Everything on this dispatch goes back to :from, as it was. Use this for a wrong branch or wrong items, before the branch receives it.', ['from' => $transfer->fromWarehouse?->name]) }}</p>
                            <input type="text" wire:model="cancelReason" placeholder="{{ __('Reason, e.g. sent to the wrong branch') }}" class="form-field w-full">
                            @error('cancelReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2">
                                <button type="button" wire:click="cancelDispatch" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700"><i class="fas fa-ban"></i>{{ __('Cancel dispatch') }}</button>
                                <button type="button" @click="open = false" class="btn-secondary py-2 text-sm">{{ __('Keep it') }}</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-page-container>
</div>
