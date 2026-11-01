<div>
    <x-page-container title="{{ __('Where is this item?') }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Item lookup')]]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <x-tracking.scanner id="lookup-reader" />
                <x-tracking.flash :flash="$flash" />

                @if ($missing->isNotEmpty())
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                        <h3 class="font-semibold text-red-800 dark:text-red-300 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>{{ __('Missing items') }}</h3>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($missing as $m)
                                <button type="button" wire:click="$set('code', '{{ $m->code }}')" class="px-2 py-0.5 rounded bg-white dark:bg-gray-800 text-xs font-mono text-red-700 dark:text-red-300 border border-red-200" title="{{ $m->product?->name }}">{{ $m->code }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="panel p-4">
                @if ($item)
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-mono text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $item->code }}</div>
                            <div class="text-gray-700 dark:text-gray-300">{{ $item->product?->name }}</div>
                            @if ($item->batch)
                                <div class="text-xs text-gray-500">{{ __('Batch') }} {{ $item->batch }}</div>
                            @endif
                        </div>
                        <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $item->status->color() }}">{{ $item->status->label() }}</span>
                    </div>

                    <div class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-900 p-4 flex items-center gap-3">
                        <i class="fas fa-map-marker-alt text-2xl text-primary-600"></i>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Currently') }}</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $item->whereabouts() ?: '—' }}</div>
                        </div>
                    </div>

                    @if ($item->box_id)
                        <a href="{{ route('tracking.lookup', ['code' => $item->box?->code]) }}" wire:navigate class="mt-3 inline-flex items-center gap-2 text-sm text-primary-600 hover:underline">
                            <i class="fas fa-box"></i>{{ __('Inside box :code', ['code' => $item->box?->code]) }}
                        </a>
                    @endif

                    @php $sum = $analytics->summary(); @endphp
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3"><div class="text-xs text-gray-500">{{ __('Customers served') }}</div><div class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($sum['customers']) }}</div></div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3"><div class="text-xs text-gray-500">{{ __('Refills') }}</div><div class="text-lg font-bold text-gray-900 dark:text-white">{{ $item->product?->is_refillable ? $sum['refills'] : __('Not refillable') }}</div></div>
                    </div>
                    <a href="#item-analytics" class="mt-3 inline-flex items-center gap-1.5 text-sm text-primary-600 hover:underline"><i class="fas fa-chart-bar"></i>{{ __('See the item\'s analytics below') }}</a>
                @elseif ($box)
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 font-mono text-lg font-semibold text-gray-900 dark:text-gray-100"><i class="fas fa-box text-primary-600"></i>{{ $box->code }}</div>
                            <div class="text-gray-700 dark:text-gray-300 text-sm">{{ $box->items->count() }} {{ __('units inside') }}</div>
                        </div>
                        <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $box->status->color() }}">{{ $box->status->label() }}</span>
                    </div>

                    <div class="mt-4 rounded-lg bg-gray-50 dark:bg-gray-900 p-4 flex items-center gap-3">
                        <i class="fas fa-map-marker-alt text-2xl text-primary-600"></i>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Currently') }}</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $box->whereabouts() ?: '—' }}</div>
                        </div>
                    </div>

                    <h4 class="mt-5 mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('Inside the box') }}</h4>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($box->items as $unit)
                            <a href="{{ route('tracking.lookup', ['code' => $unit->code]) }}" wire:navigate title="{{ $unit->product?->name }} · {{ $unit->status->label() }}"
                                class="px-2 py-0.5 rounded text-xs font-mono {{ $unit->status->color() }}">{{ $unit->code }}</a>
                        @empty
                            <span class="text-sm text-gray-500">{{ __('Empty – every unit has been taken out.') }}</span>
                        @endforelse
                    </div>
                    @if ($box->items->isNotEmpty())
                        <p class="mt-1 text-xs text-gray-500">{{ $box->summary() }}</p>
                    @endif

                    <h4 class="mt-5 mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('History') }}</h4>
                    <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-2 space-y-3">
                        @foreach ($history as $scan)
                            <li wire:key="bh-{{ $scan->id }}" class="relative ml-5">
                                <span class="absolute -left-[1.6rem] top-1.5 h-3 w-3 rounded-full bg-primary-600 ring-4 ring-white dark:ring-gray-900"></span>
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $scan->action->color() }}">{{ $scan->action->label() }}</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $scan->warehouse?->name }}</span>
                                    @if ($scan->transfer)
                                        <a href="{{ route('tracking.receive', $scan->transfer_id) }}" wire:navigate class="font-mono text-xs text-primary-600 hover:underline">{{ $scan->transfer->reference }}</a>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $scan->created_at->format('d M Y H:i') }} · {{ $scan->user?->name }}</div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="py-10 text-center text-gray-500">{{ __('Scan an item barcode or a box QR to see where it is and everywhere it has been.') }}</p>
                @endif
                @if ($canUndo)
                    <div x-data="{ open: false }" class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-3">
                        <button type="button" x-show="!open" @click="open = true" class="text-sm text-red-600 hover:underline"><i class="fas fa-undo mr-1"></i>{{ __('Entered by mistake? Undo this delivery entry') }}</button>
                        <div x-show="open" x-cloak class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 space-y-2">
                            <p class="text-sm text-red-900 dark:text-red-200">{{ __('Removes :code from stock. Possible only because nothing has happened to it since it was received. The undo is logged with your name and reason.', ['code' => $code]) }}</p>
                            <input type="text" wire:model="undoReason" placeholder="{{ __('Reason, e.g. wrong quantity entered') }}" class="form-field w-full">
                            @error('undoReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2">
                                <button type="button" wire:click="undoDelivery" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700"><i class="fas fa-undo"></i>{{ __('Undo delivery entry') }}</button>
                                <button type="button" @click="open = false" class="btn-secondary py-2 text-sm">{{ __('Keep it') }}</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @if ($item)
            @php
                $sum = $analytics->summary();
                $fills = $analytics->fills();
                $workers = $analytics->byWorker();
                $journey = $analytics->journey();
                $perDay = $analytics->customersPerDay();
                $hist = $analytics->history();
                $th = 'px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap';
                $td = 'px-3 py-2 text-sm text-gray-800 dark:text-gray-200';
            @endphp
            <section id="item-analytics" class="mt-6 space-y-4 scroll-mt-20">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Item analytics') }} · <span class="font-mono">{{ $item->code }}</span></h2>
                    <span class="text-xs text-gray-500">{{ $item->product?->name }}</span>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach ([
                        [__('Customers served'), number_format($sum['customers']), 'user-check', 'bg-[#1baf7a]/12 text-[#13805a] dark:text-[#7fd6b5]', $sum['last_used_at'] ? __('Last :when', ['when' => $sum['last_used_at']->diffForHumans()]) : __('Not used yet')],
                        [__('Customers per fill'), $sum['per_fill'] === null ? '—' : number_format($sum['per_fill'], 1), 'divide', 'bg-[#eda100]/15 text-[#a06d00] dark:text-[#f2c45a]', trans_choice(':n fill|:n fills', $sum['fills'], ['n' => $sum['fills']])],
                        [__('Refills'), $item->product?->is_refillable ? $sum['refills'] : '—', 'redo-alt', 'bg-[#0f9b9b]/12 text-[#0b7a7a] dark:text-[#7fd6d6]', $item->product?->is_refillable ? __('Refillable') : __('Not refillable')],
                        [__('Times dispatched'), $sum['moves'], 'truck', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]', trans_choice(':n location|:n locations', $journey->pluck('location')->unique()->count(), ['n' => $journey->pluck('location')->unique()->count()])],
                        [__('Age'), trans_choice(':n day|:n days', $sum['days_since_arrival'], ['n' => $sum['days_since_arrival']]), 'calendar-alt', 'bg-[#6250d6]/12 text-[#4f3fb8] dark:text-[#b0a6f0]', __('Arrived :date', ['date' => $sum['arrived_at']->format('j M Y')])],
                    ] as [$label, $value, $icon, $chip, $hint])
                        <div class="panel p-4 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                                <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white truncate">{{ $value }}</div>
                                <div class="text-xs text-gray-500 truncate">{{ $hint }}</div>
                            </div>
                            <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                        </div>
                    @endforeach
                </div>

                @if ($sum['current'])
                    <div class="rounded-xl bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 p-4 text-sm text-teal-900 dark:text-teal-200">
                        <i class="fas fa-hand-sparkles mr-1"></i>
                        {{ __('In use since :date at :where: :n customers so far on this fill (:d days).', ['date' => $sum['current']['opened_at']->format('j M Y'), 'where' => $sum['current']['location'], 'n' => $sum['current']['customers'], 'd' => $sum['current']['days']]) }}
                    </div>
                @endif

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                    <x-dash.card :title="__('Customers per day')" class="xl:col-span-7">
                        @if ($perDay->isNotEmpty())
                            <div wire:ignore wire:key="item-usage-{{ $item->id }}-{{ $sum['customers'] }}"
                                x-data="vizChart('bar', @js(['name' => __('Customers'), 'data' => $perDay->values()->all(), 'labels' => $perDay->keys()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('j M'))->all(), 'color' => 'aqua']))"></div>
                        @else
                            <p class="py-10 text-center text-sm text-gray-500">{{ __('No customers recorded with this item yet.') }}</p>
                        @endif
                    </x-dash.card>

                    <x-dash.card :title="__('Journey')" :subtitle="__('Where the item has been')" class="xl:col-span-5">
                        <ol class="space-y-2 text-sm">
                            @forelse ($journey as $stop)
                                <li class="flex items-center gap-3">
                                    <span class="w-6 h-6 shrink-0 rounded-full grid place-items-center text-[11px] font-semibold bg-primary-50 dark:bg-gray-800 text-primary-600">{{ $loop->iteration }}</span>
                                    <span class="flex-1 text-gray-900 dark:text-gray-100">{{ $stop['location'] }}</span>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $stop['at']->format('j M Y') }}</span>
                                </li>
                            @empty
                                <li class="text-gray-500">—</li>
                            @endforelse
                            @if ($item->status === \App\Enums\ItemStatus::IN_TRANSIT)
                                <li class="flex items-center gap-3 text-amber-700 dark:text-amber-300"><i class="fas fa-truck w-6 text-center"></i>{{ $item->whereabouts() }}</li>
                            @endif
                        </ol>
                    </x-dash.card>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                    <x-dash.card :title="__('Fills')" :subtitle="__('Each time the item was opened and used until finished')" class="xl:col-span-7">
                        <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                                    <th class="{{ $th }}">#</th><th class="{{ $th }}">{{ __('Opened') }}</th><th class="{{ $th }}">{{ __('Branch') }}</th><th class="{{ $th }}">{{ __('Finished') }}</th><th class="{{ $th }} text-right">{{ __('Days') }}</th><th class="{{ $th }} text-right">{{ __('Customers') }}</th><th class="{{ $th }}">{{ __('Ended as') }}</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($fills as $f)
                                        <tr>
                                            <td class="{{ $td }} tabular-nums">{{ $f['number'] }}</td>
                                            <td class="{{ $td }} whitespace-nowrap">{{ $f['opened_at']->format('j M Y') }}</td>
                                            <td class="{{ $td }} whitespace-nowrap">{{ $f['location'] }}</td>
                                            <td class="{{ $td }} whitespace-nowrap">{{ $f['finished_at']?->format('j M Y') ?? __('In use') }}</td>
                                            <td class="{{ $td }} text-right tabular-nums">{{ $f['days'] }}</td>
                                            <td class="{{ $td }} text-right tabular-nums font-semibold">{{ $f['customers'] }}</td>
                                            <td class="{{ $td }}">@if ($f['ended'])<span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $f['ended']->color() }}">{{ $f['ended'] === \App\Enums\ScanAction::DISPOSED ? __('Disposed') : __('Sent for refill') }}</span>@else<span class="text-xs text-gray-500">—</span>@endif</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="{{ $td }} text-center text-gray-500 py-6">{{ __('Not opened yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-dash.card>

                    <x-dash.card :title="__('Customers by worker')" class="xl:col-span-5">
                        <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                                    <th class="{{ $th }}">{{ __('Worker') }}</th><th class="{{ $th }}">{{ __('Station') }}</th><th class="{{ $th }} text-right">{{ __('Customers') }}</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($workers as $w)
                                        <tr><td class="{{ $td }}">{{ $w['worker'] }}<span class="block text-xs text-gray-500">{{ $w['location'] }}</span></td><td class="{{ $td }}">{{ $w['station'] ?? '—' }}</td><td class="{{ $td }} text-right tabular-nums font-semibold">{{ $w['customers'] }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="{{ $td }} text-center text-gray-500 py-6">{{ __('No customers yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-dash.card>
                </div>

                <x-dash.card :title="__('History')" :subtitle="__('Every record, newest first. Customer scans are grouped per day.')">
                    <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-2 space-y-3">
                        @foreach ($hist as $h)
                            <li wire:key="ih-{{ $h['id'] }}" class="relative ml-5">
                                <span class="absolute -left-[1.6rem] top-1.5 h-3 w-3 rounded-full bg-primary-600 ring-4 ring-white dark:ring-gray-900"></span>
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $h['action']->color() }}">{{ $h['action']->label() }}@if ($h['customers']) ×{{ $h['customers'] }}@endif</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $h['location'] }}@if ($h['to']) <i class="fas fa-arrow-right text-xs text-gray-400"></i> {{ $h['to'] }}@endif</span>
                                    @if ($h['transfer'])
                                        <a href="{{ route('tracking.receive', $h['transfer']->id) }}" wire:navigate class="font-mono text-xs text-primary-600 hover:underline">{{ $h['transfer']->reference }}</a>
                                    @endif
                                    @if ($h['box'])<span class="font-mono text-xs text-gray-500">{{ $h['box'] }}</span>@endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $h['at']->format('d M Y H:i') }}@if ($h['by']) · {{ $h['by'] }}@endif{{ $h['note'] ? ' · ' . $h['note'] : '' }}</div>
                            </li>
                        @endforeach
                    </ol>
                </x-dash.card>
            </section>
        @endif
    </x-page-container>
</div>
