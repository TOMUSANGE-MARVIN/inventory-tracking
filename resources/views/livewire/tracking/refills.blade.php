@php
    $th = 'px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap';
    $td = 'px-4 py-2.5 text-sm text-gray-800 dark:text-gray-200';
    $atHq = $rows->filter(fn ($r) => $r['item']->warehouse_id === $hqId)->count();
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('Refills') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Refillable items that are used up. Refill one and it goes back into stock with the same barcode.') }}</p>
        </div>
        @can('transfer_create')
            <a href="{{ route('tracking.dispatch') }}" wire:navigate class="btn-secondary py-2"><i class="fas fa-truck"></i>{{ __('Send empties to Head Office') }}</a>
        @endcan
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            [__('Need refill'), $rows->count(), 'redo-alt', 'bg-[#eda100]/15 text-[#a06d00] dark:text-[#f2c45a]'],
            [__('At Head Office'), $atHq, 'warehouse', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]'],
            [__('At branches'), $rows->count() - $atHq, 'store', 'bg-[#6250d6]/12 text-[#4f3fb8] dark:text-[#b0a6f0]'],
            [__('Refilled this month'), $refilledThisMonth, 'check-circle', 'bg-[#1baf7a]/12 text-[#13805a] dark:text-[#7fd6b5]'],
        ] as [$label, $value, $icon, $chip])
            <div class="panel p-4 flex items-start justify-between gap-2">
                <div>
                    <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                    <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($value) }}</div>
                </div>
                <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="xl:col-span-5 space-y-4">
            <x-tracking.scanner id="refill-reader" />
            <x-tracking.flash :flash="$flash" />
            <p class="text-xs text-gray-500">{{ __('Scan each container after filling it. It must be at your location and marked “Needs refill”.') }}</p>

            <x-dash.card :title="__('Recently refilled')">
                <ul class="divide-y divide-gray-100 dark:divide-gray-800 text-sm -my-2">
                    @forelse ($refilled as $scan)
                        <li wire:key="rf-{{ $scan->id }}" class="py-2 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate"><span class="font-mono">{{ $scan->code }}</span> · {{ $scan->product?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $scan->warehouse?->name }} · {{ $scan->created_at->format('d M H:i') }} · {{ $scan->user?->name }}</div>
                            </div>
                            <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ \App\Enums\ScanAction::REFILLED->color() }}">{{ __('Refill no. :n', ['n' => $scan->item?->refill_count]) }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-gray-500">{{ __('Nothing refilled yet.') }}</li>
                    @endforelse
                </ul>
            </x-dash.card>
        </div>

        <x-dash.card :title="__('Waiting for a refill')" :subtitle="__('Grouped by where they are now')" class="xl:col-span-7">
            <div class="flex flex-wrap gap-2 mb-3">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search code or product…') }}" class="form-field flex-1 min-w-40 py-1.5">
                @if ($locations->count() > 1)
                    <select wire:model.live="warehouse" class="form-field py-1.5">
                        <option value="">{{ __('All locations') }}</option>
                        @foreach ($locations as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            @forelse ($byLocation as $location => $group)
                <h3 class="mt-4 first:mt-0 mb-1 text-sm font-semibold text-gray-900 dark:text-white"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>{{ $location }} <span class="font-normal text-gray-500">· {{ $group->count() }}</span></h3>
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Item') }}</th><th class="{{ $th }}">{{ __('Product') }}</th><th class="{{ $th }}">{{ __('Emptied') }}</th><th class="{{ $th }} text-right">{{ __('Clients last fill') }}</th><th class="{{ $th }} text-right">{{ __('Refills so far') }}</th><th class="{{ $th }}"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($group as $r)
                                <tr wire:key="w-{{ $r['item']->id }}">
                                    <td class="{{ $td }}"><a href="{{ route('tracking.lookup', ['code' => $r['item']->code]) }}" wire:navigate class="font-mono whitespace-nowrap text-primary-600 hover:underline">{{ $r['item']->code }}</a></td>
                                    <td class="{{ $td }}">{{ $r['item']->product?->name }}</td>
                                    <td class="{{ $td }} whitespace-nowrap">{{ $r['emptied_at']?->format('d M Y') ?? '—' }}</td>
                                    <td class="{{ $td }} text-right tabular-nums">{{ $r['clients'] }}</td>
                                    <td class="{{ $td }} text-right tabular-nums">{{ $r['item']->refill_count }}</td>
                                    <td class="{{ $td }} text-right"><button type="button" wire:click="refill({{ $r['item']->id }})" class="btn-primary px-3 py-1.5 text-xs"><i class="fas fa-fill-drip"></i>{{ __('Refilled') }}</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-gray-500"><i class="fas fa-check-circle text-[#1baf7a] mr-1"></i>{{ __('Nothing is waiting for a refill.') }}</p>
            @endforelse
        </x-dash.card>
    </div>
</div>
