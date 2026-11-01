@php
    $ugx = fn ($v) => 'UGX ' . number_format((float) $v);
    $th = 'px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap';
    $td = 'px-4 py-2.5 text-sm text-gray-800 dark:text-gray-200';
    $actionIcon = fn ($a) => match ($a) {
        \App\Enums\ScanAction::RECEIVED_STOCK => 'box-open',
        \App\Enums\ScanAction::DISPATCHED => 'truck',
        \App\Enums\ScanAction::RECEIVED_TRANSFER => 'check-circle',
        \App\Enums\ScanAction::MARKED_MISSING => 'exclamation-triangle',
        \App\Enums\ScanAction::IN_USE => 'hand-sparkles',
        \App\Enums\ScanAction::SERVED_CLIENT => 'user-check',
        default => 'recycle',
    };
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('Product analytics') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $periodFrom->format('j M Y') }} – {{ $periodTo->format('j M Y') }}
                @if ($product) · <span class="font-medium text-gray-700 dark:text-gray-200">{{ $product->name }}</span>@endif
            </p>
        </div>
        @if ($product)
            <a href="{{ route('tracking.reports', ['tab' => 'movements', 'from' => $periodFrom->toDateString(), 'to' => $periodTo->toDateString()]) }}" wire:navigate class="btn-secondary py-2"><i class="fas fa-history"></i>{{ __('All movements') }}</a>
        @endif
    </div>

    {{-- Product + date filters --}}
    <div class="panel p-3 sm:p-4 grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
        <div class="lg:col-span-5 relative" x-data="{ open: false }" @click.outside="open = false">
            <label class="text-xs text-gray-500" for="product-search">{{ __('Product') }}</label>
            @if ($product)
                <div class="mt-1 flex items-center gap-2 form-field py-1.5">
                    <i class="fas fa-tag text-primary-600"></i>
                    <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100">{{ $product->name }} <span class="font-mono text-xs text-gray-500">{{ $product->code }}</span></span>
                    <button type="button" wire:click="$set('productId', '')" class="text-xs text-primary-600 hover:underline">{{ __('Change') }}</button>
                </div>
            @else
                <input id="product-search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search"
                    @focus="open = true" @input="open = true"
                    @keydown.enter.prevent="$el.parentElement.querySelector('[data-match]')?.click()"
                    placeholder="{{ __('Type a name or scan the product barcode') }}" class="form-field block w-full mt-1 py-1.5">
                <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full max-h-72 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg">
                    @forelse ($this->matches as $m)
                        <button type="button" data-match wire:click="pick('{{ $m->id }}')" @click="open = false"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-primary-50 dark:hover:bg-gray-800 flex justify-between gap-3">
                            <span class="truncate text-gray-900 dark:text-gray-100">{{ $m->name }}</span>
                            <span class="font-mono text-xs text-gray-500 whitespace-nowrap">{{ $m->code }}</span>
                        </button>
                    @empty
                        <p class="px-3 py-3 text-sm text-gray-500">{{ __('No product matches “:q”.', ['q' => $search]) }}</p>
                    @endforelse
                </div>
            @endif
        </div>
        <div class="lg:col-span-7 flex flex-wrap items-end gap-3">
            <label class="text-xs text-gray-500">{{ __('From') }}
                <input type="date" wire:model.live="from" class="form-field block mt-1 py-1.5">
            </label>
            <label class="text-xs text-gray-500">{{ __('To') }}
                <input type="date" wire:model.live="to" class="form-field block mt-1 py-1.5">
            </label>
            <div class="flex flex-wrap gap-1.5">
                @foreach (['week' => __('This week'), 'month' => __('This month'), 'last_month' => __('Last month'), '30' => __('Last 30 days'), 'year' => __('This year')] as $key => $label)
                    <button type="button" wire:click="range('{{ $key }}')" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-primary-400">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    @if (! $product)
        <div class="panel py-16 px-6 text-center">
            <span class="mx-auto w-14 h-14 rounded-2xl grid place-items-center bg-primary-50 dark:bg-gray-800 text-primary-600"><i class="fas fa-boxes text-xl"></i></span>
            <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('Pick a product') }}</h2>
            <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">{{ __('You will see every box it was recorded in, everything that happened to each box, and where its units are now.') }}</p>
        </div>
    @else
        <div wire:loading.class="opacity-60" class="space-y-5 transition">
            {{-- Headline counts --}}
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
                @foreach ([
                    [__('Boxes recorded'), number_format($summary['boxes']), 'box', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]', null],
                    [__('Units arrived'), number_format($summary['arrived']), 'box-open', 'bg-[#1baf7a]/12 text-[#13805a] dark:text-[#7fd6b5]', $summary['arrived_loose'] ? __(':n loose', ['n' => $summary['arrived_loose']]) : null],
                    [__('Units dispatched'), number_format($summary['dispatched']), 'truck', 'bg-[#eda100]/15 text-[#a06d00] dark:text-[#f2c45a]', null],
                    [__('Delivered to branch'), number_format($summary['delivered']), 'check-circle', 'bg-[#6250d6]/12 text-[#4f3fb8] dark:text-[#b0a6f0]', null],
                    [__('Missing'), number_format($summary['missing']), 'exclamation-triangle', 'bg-[#d03b3b]/12 text-[#b02f2f] dark:text-[#f08a8a]', null],
                    [__('Clients served'), number_format($summary['clients']), 'user-check', 'bg-[#e87ba4]/15 text-[#b8457a] dark:text-[#f0a9c6]', __(':o opened · :f finished', ['o' => $summary['opened'], 'f' => $summary['finished']]) . ($sales['qty'] ? ' · ' . __(':n sold', ['n' => $sales['qty']]) : '')],
                ] as [$label, $value, $icon, $chip, $hint])
                    <div class="panel p-4 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                            <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</div>
                            @if ($hint)<div class="text-xs text-gray-500 truncate" title="{{ $hint }}">{{ $hint }}</div>@endif
                        </div>
                        <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                    </div>
                @endforeach
            </div>

            {{-- Boxes / loose items --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="flex gap-1" aria-label="{{ __('Show') }}">
                    @foreach (['boxes' => ['box', __('Boxes'), $boxes->total()], 'loose' => ['tag', __('Loose items'), $loose->total()]] as $key => [$icon, $label, $count])
                        <button type="button" wire:click="$set('section', '{{ $key }}')" @class([
                            'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition',
                            'bg-primary-600 text-white shadow-sm shadow-primary-600/30' => $shown === $key,
                            'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-800 hover:text-gray-900' => $shown !== $key,
                        ])><i class="fas fa-{{ $icon }}"></i>{{ $label }}<span @class(['text-xs px-1.5 rounded-full', 'bg-white/20' => $shown === $key, 'bg-gray-100 dark:bg-gray-800' => $shown !== $key])>{{ $count }}</span></button>
                    @endforeach
                </nav>
                <span class="text-xs text-gray-500">{{ $shown === 'loose' ? __('Items that arrived on their own (not in a box), with any record in the period') : __('Boxes with any record in the period, latest first') }}</span>
            </div>

            @if ($shown === 'boxes')

            @forelse ($boxes as $box)
                @php($d = $details[$box->id])
                <div class="panel overflow-hidden" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" wire:key="box-{{ $box->id }}">
                    <button type="button" @click="open = !open" class="w-full text-left p-4 flex flex-wrap items-center gap-x-4 gap-y-2 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <span class="w-10 h-10 shrink-0 rounded-xl grid place-items-center bg-primary-50 dark:bg-gray-800 text-primary-600"><i class="fas fa-box"></i></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ $box->code }}</span>
                                <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $box->status->color() }}">{{ $box->status->label() }}</span>
                                @if ($box->batch)<span class="text-xs text-gray-500">{{ __('Batch :b', ['b' => $box->batch]) }}</span>@endif
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                {{ trans_choice(':n unit of this product|:n units of this product', $d['mine_count'], ['n' => $d['mine_count']]) }}
                                @if ($d['others']->isNotEmpty())<span class="text-gray-500"> · {{ __('also') }} {{ $d['others']->join(', ') }}</span>@endif
                            </div>
                            @if ($d['clients_all'] > 0)
                                <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full bg-teal-100 text-teal-800"><i class="fas fa-user-check"></i>{{ trans_choice(':n client served with this box\'s items|:n clients served with this box\'s items', $d['clients_all'], ['n' => $d['clients_all']]) }}@if ($d['clients'] !== $d['clients_all']) ({{ __(':n in period', ['n' => $d['clients']]) }})@endif</div>
                            @endif
                        </div>
                        <div class="text-sm sm:text-right">
                            <div class="text-gray-900 dark:text-gray-100 font-medium"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>{{ $box->whereabouts() ?: '—' }}</div>
                            <div class="text-xs text-gray-500">{{ __('Recorded :date', ['date' => $box->created_at->format('j M Y')]) }}</div>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 transition" :class="open && 'rotate-180'"></i>
                    </button>

                    <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-800">
                        <div class="grid grid-cols-1 xl:grid-cols-12">
                            {{-- Records timeline --}}
                            <div class="xl:col-span-7 p-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ __('Box records') }}</h3>
                                @include('livewire.analytics.partials.timeline', ['events' => $d['events'], 'hidden' => $d['hidden_events'], 'actionIcon' => $actionIcon])
                            </div>

                            {{-- Units of this product --}}
                            <div class="xl:col-span-5 p-4 bg-gray-50/60 dark:bg-gray-800/30 xl:border-l border-t xl:border-t-0 border-gray-100 dark:border-gray-800">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ __('Where its units are now') }}</h3>
                                <div class="max-h-80 overflow-y-auto -mx-1">
                                    <table class="min-w-full">
                                        <thead><tr><th class="{{ $th }} px-1">{{ __('Unit') }}</th><th class="{{ $th }} px-1">{{ __('Status') }}</th><th class="{{ $th }} px-1">{{ __('Location') }}</th><th class="{{ $th }} px-1 text-right">{{ __('Clients') }}</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach ($d['units'] as $u)
                                                <tr>
                                                    <td class="py-1.5 px-1 text-sm">
                                                        <a href="{{ route('tracking.lookup', ['code' => $u['code']]) }}" wire:navigate class="font-mono text-primary-600 hover:underline whitespace-nowrap">{{ $u['code'] }}</a>
                                                        @unless ($u['inside'])<span class="block text-[11px] text-gray-500">{{ __('taken out of box') }}</span>@endunless
                                                    </td>
                                                    <td class="py-1.5 px-1">@if ($u['status'])<span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $u['status']->color() }}">{{ $u['status']->label() }}</span>@endif</td>
                                                    <td class="py-1.5 px-1 text-sm text-gray-700 dark:text-gray-300">{{ $u['location'] ?? '—' }}</td>
                                                    <td class="py-1.5 px-1 text-sm text-right tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ $u['clients'] ?: '–' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <a href="{{ route('tracking.lookup', ['code' => $box->code]) }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 text-sm text-primary-600 hover:underline"><i class="fas fa-qrcode"></i>{{ __('Open box :code', ['code' => $box->code]) }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="panel py-12 px-6 text-center text-gray-500">
                    <i class="fas fa-box-open text-3xl text-gray-300 dark:text-gray-600"></i>
                    <p class="mt-3 text-sm">{{ __('No boxes with :name had any records between these dates.', ['name' => $product->name]) }}</p>
                    @if ($loose->total())<button type="button" wire:click="$set('section', 'loose')" class="mt-2 text-sm text-primary-600 hover:underline">{{ trans_choice('See :n loose item|See :n loose items', $loose->total(), ['n' => $loose->total()]) }}</button>@endif
                </div>
            @endforelse

            @if ($boxes->hasPages())
                <div>{{ $boxes->links() }}</div>
            @endif
            @else
            {{-- Loose items: one card per item --}}
            @forelse ($loose as $item)
                @php($d = $looseDetails[$item->id])
                <div class="panel overflow-hidden" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" wire:key="loose-{{ $item->id }}">
                    <button type="button" @click="open = !open" class="w-full text-left p-4 flex flex-wrap items-center gap-x-4 gap-y-2 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <span class="w-10 h-10 shrink-0 rounded-xl grid place-items-center bg-primary-50 dark:bg-gray-800 text-primary-600"><i class="fas fa-tag"></i></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ $item->code }}</span>
                                <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $item->status->color() }}">{{ $item->status->label() }}</span>
                                @if ($item->refill_count)<span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ \App\Enums\ScanAction::REFILLED->color() }}">{{ trans_choice('Refilled :n time|Refilled :n times', $item->refill_count, ['n' => $item->refill_count]) }}</span>@endif
                                @if ($item->batch)<span class="text-xs text-gray-500">{{ __('Batch :b', ['b' => $item->batch]) }}</span>@endif
                            </div>
                            @if ($d['clients_all'] > 0)
                                <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full bg-teal-100 text-teal-800"><i class="fas fa-user-check"></i>{{ trans_choice(':n client served|:n clients served', $d['clients_all'], ['n' => $d['clients_all']]) }}@if ($d['clients'] !== $d['clients_all']) ({{ __(':n in period', ['n' => $d['clients']]) }})@endif</div>
                            @endif
                        </div>
                        <div class="text-sm sm:text-right">
                            <div class="text-gray-900 dark:text-gray-100 font-medium"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>{{ $item->whereabouts() ?: '—' }}</div>
                            <div class="text-xs text-gray-500">{{ __('Arrived :date', ['date' => ($d['arrived_at'] ?? $item->created_at)->format('j M Y')]) }}</div>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 transition" :class="open && 'rotate-180'"></i>
                    </button>
                    <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-800 p-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Item records') }}</h3>
                            <a href="{{ route('tracking.lookup', ['code' => $item->code]) }}" wire:navigate class="text-sm text-primary-600 hover:underline"><i class="fas fa-search mr-1"></i>{{ __('Full history') }}</a>
                        </div>
                        @include('livewire.analytics.partials.timeline', ['events' => $d['events'], 'hidden' => $d['hidden_events'], 'actionIcon' => $actionIcon, 'single' => true])
                    </div>
                </div>
            @empty
                <div class="panel py-12 px-6 text-center text-gray-500">
                    <i class="fas fa-tag text-3xl text-gray-300 dark:text-gray-600"></i>
                    <p class="mt-3 text-sm">{{ __('No loose items of :name had any records between these dates.', ['name' => $product->name]) }}</p>
                </div>
            @endforelse

            @if ($loose->hasPages())
                <div>{{ $loose->links() }}</div>
            @endif
            @endif
        </div>
    @endif
</div>
