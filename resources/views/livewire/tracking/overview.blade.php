@php
    // One colour per metric, light + dark steps from the validated palette (literal classes so Tailwind builds them).
    $c = [
        'aqua' => ['dot' => 'bg-[#1baf7a] dark:bg-[#199e70]', 'chip' => 'bg-[#1baf7a]/12 text-[#0f8a5f] dark:bg-[#199e70]/20 dark:text-[#5fd3a8]', 'bar' => 'from-[#1baf7a] to-[#4fd1a5] dark:from-[#199e70] dark:to-[#2fbf8b]'],
        'violet' => ['dot' => 'bg-[#6250d6] dark:bg-[#9085e9]', 'chip' => 'bg-[#6250d6]/12 text-[#4f3fc0] dark:bg-[#9085e9]/20 dark:text-[#b3abf2]', 'bar' => 'from-[#6250d6] to-[#8e80ea] dark:from-[#9085e9] dark:to-[#aea6f0]'],
        'blue' => ['dot' => 'bg-[#2a78d6] dark:bg-[#3987e5]', 'chip' => 'bg-[#2a78d6]/12 text-[#1f62b3] dark:bg-[#3987e5]/20 dark:text-[#8db8f0]', 'bar' => 'from-[#2a78d6] to-[#5598e7] dark:from-[#3987e5] dark:to-[#6da7ec]'],
        'orange' => ['dot' => 'bg-[#eb6834] dark:bg-[#d95926]', 'chip' => 'bg-[#eb6834]/12 text-[#c24e1d] dark:bg-[#d95926]/20 dark:text-[#f2a07e]', 'bar' => 'from-[#eb6834] to-[#f39066] dark:from-[#d95926] dark:to-[#e8814f]'],
        'yellow' => ['dot' => 'bg-[#eda100] dark:bg-[#c98500]', 'chip' => 'bg-[#eda100]/15 text-[#a06d00] dark:bg-[#c98500]/20 dark:text-[#f2c45a]', 'bar' => 'from-[#eda100] to-[#f5bf3f] dark:from-[#c98500] dark:to-[#dba032]'],
        'red' => ['dot' => 'bg-[#d03b3b]', 'chip' => 'bg-[#d03b3b]/12 text-[#b02f2f] dark:bg-[#d03b3b]/25 dark:text-[#f08a8a]', 'bar' => 'from-[#d03b3b] to-[#e66767]'],
    ];
    // Location donut: categorical slots in fixed location order.
    $swatch = [
        'bg-[#2a78d6] dark:bg-[#3987e5]', 'bg-[#eb6834] dark:bg-[#d95926]', 'bg-[#1baf7a] dark:bg-[#199e70]', 'bg-[#eda100] dark:bg-[#c98500]',
        'bg-[#e87ba4] dark:bg-[#d55181]', 'bg-[#008300] dark:bg-[#008300]', 'bg-[#6250d6] dark:bg-[#9085e9]', 'bg-[#e34948] dark:bg-[#e66767]',
    ];
    $totalUnits = array_sum(array_column($locations, 'units'));
    $totalItems = array_sum(array_column($statuses, 'value'));
    $topMax = max(1, ...(array_column($topProducts, 'units') ?: [1]));
    $todayMax = max(1, ...array_column($today, 'value'));
@endphp

<div class="space-y-5">
    {{-- Header + filters in one row --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('Dashboard') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $scopeLabel }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-0.5" role="group" aria-label="{{ __('Period') }}">
                @foreach ($periods as $p)
                    <button type="button" wire:click="$set('period', {{ $p }})" @class([
                        'px-3 py-1.5 text-xs font-medium rounded-md transition',
                        'bg-primary-600 text-white shadow-sm' => $period === $p,
                        'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' => $period !== $p,
                    ])>{{ $p }} {{ __('days') }}</button>
                @endforeach
            </div>
            <a href="{{ route('tracking.arrivals') }}" wire:navigate class="btn-secondary py-2"><i class="fas fa-box-open text-primary-600"></i>{{ __('Receive stock') }}</a>
            <a href="{{ route('tracking.dispatch') }}" wire:navigate class="btn-primary py-2"><i class="fas fa-truck"></i>{{ __('New dispatch') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12">
        {{-- Stat tiles, each in its metric colour --}}
        @foreach ($tiles as $i => $tile)
            <x-dash.card wire:key="tile-{{ $i }}-{{ $period }}" class="xl:col-span-3 !pb-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $tile['label'] }}</span>
                        <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($tile['value']) }}</div>
                    </div>
                    <span class="w-10 h-10 shrink-0 rounded-xl grid place-items-center {{ $c[$tile['color']]['chip'] }}"><i class="fas fa-{{ $tile['icon'] }}"></i></span>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($tile['change'] !== null)
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md font-semibold {{ $c[$tile['color']]['chip'] }}">
                            <i class="fas fa-arrow-{{ $tile['change'] >= 0 ? 'up' : 'down' }} text-[9px]"></i>{{ abs($tile['change']) }}%
                        </span>
                        {{ __('vs previous :n days', ['n' => $period]) }}
                    @elseif ($tile['hasPrevious'])
                        {{ __('last :n days', ['n' => $period]) }}
                    @else
                        {{ __('right now · trend over :n days', ['n' => $period]) }}
                    @endif
                </div>
                <div class="mt-1" wire:ignore wire:key="spark-{{ $i }}-{{ $period }}"
                    x-data="vizChart('spark', @js(['name' => $tile['label'], 'data' => $tile['series'], 'labels' => $labels, 'color' => $tile['color']]))"></div>
            </x-dash.card>
        @endforeach

        {{-- Movement over time --}}
        <x-dash.card :title="__('Stock movement')" :subtitle="__('Units dispatched vs received at branches, per day')" class="md:col-span-2 xl:col-span-6">
            <div wire:ignore wire:key="movement-{{ $period }}"
                x-data="vizChart('area', @js(['series' => $movement, 'labels' => $labels]))"></div>
        </x-dash.card>

        {{-- Units by location --}}
        <x-dash.card :title="__('Units by location')" :subtitle="__('Items in stock now')" class="xl:col-span-3">
            <div wire:ignore wire:key="donut-loc-{{ $totalUnits }}"
                x-data="vizChart('donut', @js(['data' => array_column($locations, 'units'), 'labels' => array_column($locations, 'name'), 'totalLabel' => __('In stock')]))"></div>
            <ul class="mt-3 grid grid-cols-1 gap-1.5 text-sm">
                @foreach ($locations as $i => $loc)
                    <li class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm shrink-0 {{ $swatch[$i % 8] }}"></span>
                        <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ $loc['name'] }}</span>
                        <span class="tabular-nums font-medium text-gray-900 dark:text-white">{{ number_format($loc['units']) }}</span>
                        <span class="w-9 text-right tabular-nums text-xs text-gray-500">{{ $totalUnits ? round($loc['units'] / $totalUnits * 100) : 0 }}%</span>
                    </li>
                @endforeach
            </ul>
        </x-dash.card>

        {{-- Units by status --}}
        <x-dash.card :title="__('Units by status')" :subtitle="__('Every labelled unit still in circulation')" class="xl:col-span-3">
            <div wire:ignore wire:key="donut-status-{{ $totalItems }}"
                x-data="vizChart('donut', @js(['data' => array_column($statuses, 'value'), 'labels' => array_column($statuses, 'label'), 'colors' => array_column($statuses, 'color'), 'totalLabel' => __('Units')]))"></div>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($statuses as $s)
                    <li class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm shrink-0 {{ $c[$s['color']]['dot'] }}"></span>
                        <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ $s['label'] }}</span>
                        <span class="tabular-nums font-medium text-gray-900 dark:text-white">{{ number_format($s['value']) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-dash.card>

        {{-- Daily usage --}}
        <x-dash.card :title="__('Units put into use')" :subtitle="__('Per day')" class="md:col-span-2 xl:col-span-6">
            <div wire:ignore wire:key="usage-{{ $period }}"
                x-data="vizChart('bar', @js(['name' => __('Put into use'), 'data' => $usage, 'labels' => $labels, 'color' => 'orange']))"></div>
        </x-dash.card>

        {{-- Today --}}
        <x-dash.card :title="__('Today')" :subtitle="now()->format('l j M')" class="xl:col-span-3">
            <ul class="space-y-3.5">
                @foreach ($today as $row)
                    <li class="flex items-center gap-3">
                        <span class="w-1 self-stretch rounded-full {{ $c[$row['color']]['dot'] }}"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300 truncate">{{ $row['label'] }}</span>
                                <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ $row['value'] }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r {{ $c[$row['color']]['bar'] }}" style="width: {{ $row['value'] / $todayMax * 100 }}%"></div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-dash.card>

        {{-- Needs attention --}}
        <x-dash.card :title="__('Needs attention')" class="xl:col-span-3">
            <a href="{{ route('tracking.lookup') }}" wire:navigate class="flex items-center gap-3 rounded-xl p-3 {{ $missing ? 'bg-[#d03b3b]/10' : 'bg-gray-50 dark:bg-gray-800/60' }}">
                <span class="w-10 h-10 rounded-xl grid place-items-center {{ $missing ? 'bg-[#d03b3b] text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}"><i class="fas fa-exclamation-triangle"></i></span>
                <span>
                    <span class="block text-2xl font-bold text-gray-900 dark:text-white leading-tight">{{ $missing }}</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-300">{{ __('Missing items') }}</span>
                </span>
            </a>

            <h3 class="mt-4 mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('In transit') }}</h3>
            <ul class="space-y-1.5 text-sm">
                @forelse ($inTransit as $t)
                    <li><a href="{{ route('tracking.receive', $t) }}" wire:navigate class="flex justify-between gap-2 hover:text-primary-600">
                        <span class="truncate text-gray-700 dark:text-gray-300"><span class="inline-block w-2 h-2 rounded-full mr-1.5 {{ $c['blue']['dot'] }}"></span>{{ $t->toWarehouse?->name }}</span>
                        <span class="shrink-0 text-xs text-gray-500">{{ $t->total_qty }} · {{ $t->dispatched_at?->diffForHumans(short: true) }}</span>
                    </a></li>
                @empty
                    <li class="text-gray-500">{{ __('Nothing on the road.') }}</li>
                @endforelse
            </ul>

            @if ($flagged->isNotEmpty())
                <h3 class="mt-4 mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Received short') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($flagged as $t)
                        <li><a href="{{ route('tracking.receive', $t) }}" wire:navigate class="flex justify-between gap-2 text-red-700 dark:text-red-300 hover:underline">
                            <span class="truncate">{{ $t->reference }}</span>
                            <span class="shrink-0 text-xs">{{ $t->toWarehouse?->name }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </x-dash.card>

        {{-- Top products --}}
        <x-dash.card :title="__('Most stocked products')" :subtitle="__('Units in stock')" class="md:col-span-2 xl:col-span-4">
            <ul class="space-y-3">
                @foreach ($topProducts as $p)
                    <li>
                        <div class="flex justify-between gap-2 text-sm">
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $p['name'] }}</span>
                            <span class="tabular-nums font-medium text-gray-900 dark:text-white">{{ number_format($p['units']) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r {{ $c['aqua']['bar'] }}" style="width: {{ $p['units'] / $topMax * 100 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-dash.card>

        {{-- Full table: the exact numbers behind the charts --}}
        <x-dash.card :title="__('Stock by location')" :subtitle="__('Units available at each location')" class="md:col-span-2 xl:col-span-8">
            <x-slot name="action">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search product or code…') }}" class="form-field w-40 sm:w-56 py-1.5">
            </x-slot>
            <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 dark:text-gray-400 border-y border-gray-100 dark:border-gray-800">
                        <tr>
                            <th class="px-4 sm:px-5 py-2.5 text-left font-medium sticky left-0 bg-white dark:bg-gray-900">{{ __('Product') }}</th>
                            @foreach ($warehouses as $w)
                                <th class="px-3 py-2.5 text-right font-medium whitespace-nowrap">{{ \Illuminate\Support\Str::before($w->name, ' Branch') }}</th>
                            @endforeach
                            <th class="px-4 sm:px-5 py-2.5 text-right font-medium whitespace-nowrap">{{ __('In transit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($products as $p)
                            <tr wire:key="ov-{{ $p->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 sm:px-5 py-2.5 sticky left-0 bg-white dark:bg-gray-900 min-w-48">
                                    <a href="{{ route('tracking.history', ['product' => $p->id]) }}" wire:navigate class="font-medium text-gray-900 dark:text-white hover:text-primary-600">{{ $p->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $p->code }}</div>
                                </td>
                                @foreach ($warehouses as $w)
                                    @php($n = (int) ($qty[$p->id][$w->id] ?? 0))
                                    <td @class(['px-3 py-2.5 text-right tabular-nums', 'text-gray-300 dark:text-gray-600' => $n === 0, 'text-gray-900 dark:text-gray-100 font-medium' => $n > 0])>{{ $n }}</td>
                                @endforeach
                                <td class="px-4 sm:px-5 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ (int) ($transitQty[$p->id] ?? 0) ?: '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dash.card>
    </div>
</div>
