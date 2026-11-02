@php
    $ugx = fn ($v) => 'UGX ' . number_format((float) $v);
    // Tiles use a short form so large amounts fit (exact figure on hover and in the tables).
    $ugxShort = fn ($v) => 'UGX ' . match (true) {
        abs((float) $v) >= 1_000_000 => rtrim(rtrim(number_format($v / 1_000_000, 1), '0'), '.') . 'M',
        abs((float) $v) >= 10_000 => rtrim(rtrim(number_format($v / 1_000, 1), '0'), '.') . 'K',
        default => number_format((float) $v),
    };
    $tabs = [
        'losses' => ['exclamation-triangle', __('Losses')],
        'transfers' => ['truck', __('Transfers')],
        'usage' => ['user-check', __('Usage')],
        'compare' => ['balance-scale', __('Branch comparison')],
        'value' => ['coins', __('Stock value')],
        'movements' => ['history', __('Movements')],
        'corrections' => ['undo', __('Corrections')],
    ];
    $period = \Illuminate\Support\Carbon::parse($from)->format('j M Y') . ' – ' . \Illuminate\Support\Carbon::parse($to)->format('j M Y');
    $th = 'px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap';
    $td = 'px-4 py-2.5 text-sm text-gray-800 dark:text-gray-200';
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('Reports') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tab === 'value' ? __('Stock on hand right now') : $period }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tracking.reports.export') }}?{{ $this->exportQuery('xlsx') }}" class="btn-secondary py-2"><i class="fas fa-file-excel text-[#1baf7a]"></i>{{ __('Excel') }}</a>
            <a href="{{ route('tracking.reports.export') }}?{{ $this->exportQuery('pdf') }}" target="_blank" class="btn-secondary py-2"><i class="fas fa-file-pdf text-[#d03b3b]"></i>{{ __('PDF') }}</a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="panel p-3 sm:p-4 flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-1.5">
            @foreach (['week' => __('This week'), 'month' => __('This month'), 'last_month' => __('Last month'), '30' => __('Last 30 days')] as $key => $label)
                <button type="button" wire:click="range('{{ $key }}')" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-primary-400">{{ $label }}</button>
            @endforeach
        </div>
        <label class="text-xs text-gray-500">{{ __('From') }}
            <input type="date" wire:model.live="from" @disabled($tab === 'value') class="form-field block mt-1 py-1.5">
        </label>
        <label class="text-xs text-gray-500">{{ __('To') }}
            <input type="date" wire:model.live="to" @disabled($tab === 'value') class="form-field block mt-1 py-1.5">
        </label>
        @if ($locations->count() > 1)
            <label class="text-xs text-gray-500">{{ __('Location') }}
                <select wire:model.live="warehouse" class="form-field block mt-1 py-1.5">
                    <option value="">{{ __('All locations') }}</option>
                    @foreach ($locations as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    {{-- Tabs --}}
    <nav class="flex gap-1 overflow-x-auto -mx-1 px-1" aria-label="{{ __('Report') }}">
        @foreach ($tabs as $key => [$icon, $label])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition',
                'bg-primary-600 text-white shadow-sm shadow-primary-600/30' => $tab === $key,
                'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-800 hover:text-gray-900' => $tab !== $key,
            ])><i class="fas fa-{{ $icon }}"></i>{{ $label }}</button>
        @endforeach
    </nav>

    <div wire:loading.class="opacity-60" class="space-y-4 transition">
    @if ($tab === 'losses')
        {{-- ============================== LOSSES ============================== --}}
        @php($t = $data['totals'])
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ([
                [__('Units missing'), number_format($t['units']), 'exclamation-triangle', 'bg-[#d03b3b]/12 text-[#b02f2f] dark:text-[#f08a8a]'],
                [__('Value lost (cost)'), $ugxShort($t['cost']), 'coins', 'bg-[#d03b3b]/12 text-[#b02f2f] dark:text-[#f08a8a]'],
                [__('Loss rate'), $t['rate'] === null ? '—' : $t['rate'] . '%', 'percent', 'bg-[#eb6834]/12 text-[#c24e1d] dark:text-[#f2a07e]'],
                [__('Transfers affected'), number_format($t['transfers']), 'truck', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]'],
            ] as [$label, $value, $icon, $chip])
                <div class="panel p-4 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                        <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white truncate" title="{{ $value }}">{{ $value }}</div>
                    </div>
                    <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">{{ __('Loss rate = units missing ÷ units sent to that location in the period (:n sent). Value at cost; retail value :retail.', ['n' => number_format($t['sent']), 'retail' => $ugx($t['price'])]) }}</p>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <x-dash.card :title="__('By location')" :subtitle="__('Where units go missing')" class="xl:col-span-7">
                @if ($data['byLocation']->isNotEmpty())
                    <div wire:ignore wire:key="loss-chart-{{ md5(json_encode($data['byLocation'])) }}"
                        x-data="vizChart('bar', @js(['name' => __('Units missing'), 'data' => $data['byLocation']->pluck('units')->all(), 'labels' => $data['byLocation']->pluck('location')->map(fn ($n) => \Illuminate\Support\Str::before($n, ' Branch'))->all(), 'color' => 'red']))"></div>
                @endif
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Location') }}</th><th class="{{ $th }} text-right">{{ __('Sent') }}</th><th class="{{ $th }} text-right">{{ __('Missing') }}</th><th class="{{ $th }} text-right">{{ __('Rate') }}</th><th class="{{ $th }} text-right">{{ __('Value (cost)') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($data['byLocation'] as $r)
                                <tr><td class="{{ $td }}">{{ $r['location'] }}</td><td class="{{ $td }} text-right tabular-nums">{{ $r['sent'] }}</td>
                                    <td class="{{ $td }} text-right tabular-nums font-semibold {{ $r['units'] ? 'text-[#b02f2f] dark:text-[#f08a8a]' : '' }}">{{ $r['units'] }}</td>
                                    <td class="{{ $td }} text-right tabular-nums">{{ $r['rate'] === null ? '—' : $r['rate'] . '%' }}</td><td class="{{ $td }} text-right tabular-nums">{{ $ugx($r['cost']) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No transfers to these locations in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dash.card>

            <x-dash.card :title="__('By product')" :subtitle="__('What goes missing')" class="xl:col-span-5">
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Product') }}</th><th class="{{ $th }} text-right">{{ __('Units') }}</th><th class="{{ $th }} text-right">{{ __('Value (cost)') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($data['byProduct'] as $r)
                                <tr><td class="{{ $td }}">{{ $r['product'] }}</td><td class="{{ $td }} text-right tabular-nums">{{ $r['units'] }}</td><td class="{{ $td }} text-right tabular-nums">{{ $ugx($r['cost']) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="{{ $td }} text-center text-gray-500 py-8"><i class="fas fa-check-circle text-[#1baf7a] mr-1"></i>{{ __('Nothing went missing in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dash.card>
        </div>

        <x-dash.card :title="__('Every missing unit')" :subtitle="__('Which unit, from which box and transfer, and who sent and received it')">
            <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                        @foreach ([__('Date'), __('Unit'), __('Product'), __('Box'), __('At'), __('Transfer'), __('Sent by'), __('Received by'), __('Cost')] as $h)
                            <th class="{{ $th }} {{ $loop->last ? 'text-right' : '' }}">{{ $h }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['rows'] as $r)
                            <tr>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['date']->format('d M Y') }}</td>
                                <td class="{{ $td }}"><a href="{{ route('tracking.lookup', ['code' => $r['code']]) }}" wire:navigate class="font-mono whitespace-nowrap text-primary-600 hover:underline">{{ $r['code'] }}</a></td>
                                <td class="{{ $td }}">{{ $r['product'] }}</td>
                                <td class="{{ $td }} font-mono whitespace-nowrap text-xs">{{ $r['box'] ?? '—' }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['location'] }}</td>
                                <td class="{{ $td }}">@if ($r['transfer_id'])<a href="{{ route('tracking.receive', $r['transfer_id']) }}" wire:navigate class="font-mono whitespace-nowrap text-xs text-primary-600 hover:underline">{{ $r['transfer'] }}</a>@endif</td>
                                <td class="{{ $td }}">{{ $r['dispatched_by'] }}</td>
                                <td class="{{ $td }}">{{ $r['received_by'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($r['cost']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No missing units in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-dash.card>

    @elseif ($tab === 'transfers')
        {{-- ============================== TRANSFERS ============================== --}}
        @php($t = $data['totals'])
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ([
                [__('Transfers'), number_format($t['count']) . ' · ' . number_format($t['units']) . ' ' . __('units'), 'truck', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]'],
                [__('Average delivery time'), $t['avg_hours'] === null ? '—' : $t['avg_hours'] . ' h', 'stopwatch', 'bg-[#6250d6]/12 text-[#4f3fc0] dark:text-[#b3abf2]'],
                [__('Arrived complete'), $t['full_rate'] === null ? '—' : $t['full_rate'] . '%', 'check-circle', 'bg-[#1baf7a]/12 text-[#0f8a5f] dark:text-[#5fd3a8]'],
                [__('Still on the road'), $t['in_transit'] . ($t['overdue'] ? ' · ' . $t['overdue'] . ' ' . __('over 24 h') : ''), 'road', 'bg-[#eb6834]/12 text-[#c24e1d] dark:text-[#f2a07e]'],
            ] as [$label, $value, $icon, $chip])
                <div class="panel p-4 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                        <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white truncate" title="{{ $value }}">{{ $value }}</div>
                    </div>
                    <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                </div>
            @endforeach
        </div>
        @if ($t['slowest'])
            <p class="text-xs text-gray-500">{{ __('Slowest delivery: :ref, :from → :to, :h hours.', ['ref' => $t['slowest']['reference'], 'from' => $t['slowest']['from'], 'to' => $t['slowest']['to'], 'h' => $t['slowest']['hours']]) }}</p>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <x-dash.card :title="__('All transfers')" :subtitle="__('Dispatch to receipt, with who handled each step')" class="xl:col-span-8">
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            @foreach ([__('Transfer'), __('Route'), __('Sent'), __('Received'), __('Time'), __('Units'), __('Status')] as $h)
                                <th class="{{ $th }}">{{ $h }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($data['rows'] as $r)
                                <tr>
                                    <td class="{{ $td }}"><a href="{{ route('tracking.receive', $r['id']) }}" wire:navigate class="font-mono whitespace-nowrap text-xs text-primary-600 hover:underline">{{ $r['reference'] }}</a></td>
                                    <td class="{{ $td }} whitespace-nowrap">{{ \Illuminate\Support\Str::before($r['from'], ' Branch') }} → {{ \Illuminate\Support\Str::before($r['to'], ' Branch') }}</td>
                                    <td class="{{ $td }} whitespace-nowrap">{{ $r['dispatched_at']->format('d M H:i') }}<div class="text-xs text-gray-500">{{ $r['dispatched_by'] }}</div></td>
                                    <td class="{{ $td }} whitespace-nowrap">{{ $r['received_at']?->format('d M H:i') ?? '—' }}<div class="text-xs text-gray-500">{{ $r['received_by'] }}</div></td>
                                    <td class="{{ $td }} whitespace-nowrap tabular-nums {{ $r['hours'] > 24 ? 'text-[#c24e1d] font-semibold' : '' }}">{{ $r['hours'] }} h</td>
                                    <td class="{{ $td }} whitespace-nowrap tabular-nums">{{ $r['received'] ?? '–' }} / {{ $r['sent'] }}@if ($r['missing'])<span class="ml-1 text-xs text-[#b02f2f]">−{{ $r['missing'] }}</span>@endif</td>
                                    <td class="{{ $td }}"><span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $r['status']->color() }}">{{ $r['status']->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No transfers in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dash.card>

            <x-dash.card :title="__('People')" :subtitle="__('Who dispatched and who received')" class="xl:col-span-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($data['staff'] as $p)
                        <li class="py-2.5 flex items-center gap-3">
                            <span class="w-8 h-8 shrink-0 rounded-full bg-primary-600 text-white grid place-items-center text-xs font-semibold">{{ strtoupper(mb_substr($p['name'], 0, 1)) }}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-gray-900 dark:text-white truncate">{{ $p['name'] }}</span>
                                <span class="block text-xs text-gray-500">{{ __(':d dispatched (:u units) · :r received', ['d' => $p['dispatched'], 'u' => $p['units_sent'], 'r' => $p['received']]) }}</span>
                            </span>
                            @if ($p['short'])
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#d03b3b]/12 text-[#b02f2f] dark:text-[#f08a8a]" title="{{ __('Receipts that came up short') }}"><i class="fas fa-exclamation-triangle"></i> {{ $p['short'] }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-gray-500">{{ __('Nobody handled transfers in this period.') }}</li>
                    @endforelse
                </ul>
            </x-dash.card>
        </div>

    @elseif ($tab === 'usage')
        {{-- ============================== USAGE ============================== --}}
        @php($t = $data['totals'])
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ([
                [__('Clients served'), number_format($t['clients']), 'user-check', 'bg-[#1baf7a]/12 text-[#13805a] dark:text-[#7fd6b5]'],
                [__('Items used'), number_format($t['units']), 'pump-soap', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]'],
                [__('Items finished'), number_format($t['finished']), 'recycle', 'bg-[#6250d6]/12 text-[#4f3fb8] dark:text-[#b0a6f0]'],
                [__('Clients per item'), $t['per_unit'] === null ? '—' : number_format($t['per_unit'], 1), 'divide', 'bg-[#eda100]/15 text-[#a06d00] dark:text-[#f2c45a]'],
            ] as [$label, $value, $icon, $chip])
                <div class="panel p-4 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                        <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white truncate">{{ $value }}</div>
                    </div>
                    <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">{{ __('Each scan at the scan station in “Client served” mode is one client. Clients per item = all clients served by the items finished in this period ÷ items finished.') }}</p>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <x-dash.card :title="__('Clients served per day')" class="xl:col-span-7">
                @if ($t['clients'] > 0)
                    <div wire:ignore wire:key="usage-daily-{{ md5(json_encode($data['daily'])) }}"
                        x-data="vizChart('bar', @js(['name' => __('Clients'), 'data' => $data['daily']->values()->all(), 'labels' => $data['daily']->keys()->all(), 'color' => 'aqua']))"></div>
                @else
                    <p class="py-10 text-center text-sm text-gray-500">{{ __('No clients recorded in this period. Use the scan station in “Client served” mode.') }}</p>
                @endif
            </x-dash.card>

            <x-dash.card :title="__('By branch')" :subtitle="__('Clients and items used at each location')" class="xl:col-span-5">
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Location') }}</th><th class="{{ $th }} text-right">{{ __('Clients') }}</th><th class="{{ $th }} text-right">{{ __('Items used') }}</th><th class="{{ $th }} text-right">{{ __('Finished') }}</th><th class="{{ $th }} text-right">{{ __('Per item') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($data['byLocation'] as $r)
                                <tr><td class="{{ $td }}">{{ $r['location'] }}</td>
                                    <td class="{{ $td }} text-right tabular-nums font-semibold">{{ number_format($r['clients']) }}</td>
                                    <td class="{{ $td }} text-right tabular-nums">{{ $r['units'] }}</td>
                                    <td class="{{ $td }} text-right tabular-nums">{{ $r['finished'] }}</td>
                                    <td class="{{ $td }} text-right tabular-nums">{{ $r['per_unit'] === null ? '—' : number_format($r['per_unit'], 1) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dash.card>
        </div>

        <x-dash.card :title="__('By product')" :subtitle="__('How many clients each item serves, and what that costs per client')">
            <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                        <th class="{{ $th }}">{{ __('Product') }}</th><th class="{{ $th }} text-right">{{ __('Clients') }}</th><th class="{{ $th }} text-right">{{ __('Items used') }}</th><th class="{{ $th }} text-right">{{ __('Opened') }}</th><th class="{{ $th }} text-right">{{ __('Finished') }}</th><th class="{{ $th }} text-right">{{ __('Clients per item') }}</th><th class="{{ $th }} text-right">{{ __('Cost per client') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['byProduct'] as $r)
                            <tr><td class="{{ $td }}">{{ $r['product'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums font-semibold">{{ number_format($r['clients']) }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $r['units'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $r['opened'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $r['finished'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $r['per_unit'] === null ? '—' : number_format($r['per_unit'], 1) }}</td>
                                <td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $r['cost_per_client'] === null ? '—' : $ugx($r['cost_per_client']) }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No usage recorded in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-dash.card>

        <x-dash.card :title="__('By worker & station')" :subtitle="__('Who served the clients, and where')">
            <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                        <th class="{{ $th }}">{{ __('Worker') }}</th><th class="{{ $th }}">{{ __('Station') }}</th><th class="{{ $th }}">{{ __('Branch') }}</th><th class="{{ $th }} text-right">{{ __('Clients') }}</th><th class="{{ $th }} text-right">{{ __('Items used') }}</th><th class="{{ $th }}">{{ __('Last scan') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['byWorker'] as $r)
                            <tr><td class="{{ $td }} font-medium">{{ $r['worker'] }}</td><td class="{{ $td }}">{{ $r['station'] ?? '—' }}</td><td class="{{ $td }} whitespace-nowrap">{{ $r['location'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums font-semibold">{{ number_format($r['clients']) }}</td><td class="{{ $td }} text-right tabular-nums">{{ $r['items'] }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['last_at']->format('d M H:i') }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No client scans in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-dash.card>

        <x-dash.card :title="__('Every item used')" :subtitle="__('Clients served by each labelled item in the period, and over its whole life')">
            <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                        @foreach ([__('Item'), __('Product'), __('Branch'), __('Status'), __('Clients (period)'), __('Clients (all time)'), __('First used'), __('Last used')] as $h)
                            <th class="{{ $th }} {{ in_array($loop->index, [4, 5]) ? 'text-right' : '' }}">{{ $h }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['rows']->take(200) as $r)
                            <tr>
                                <td class="{{ $td }}"><a href="{{ route('tracking.lookup', ['code' => $r['code']]) }}" wire:navigate class="font-mono whitespace-nowrap text-primary-600 hover:underline">{{ $r['code'] }}</a></td>
                                <td class="{{ $td }}">{{ $r['product'] }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['location'] }}</td>
                                <td class="{{ $td }}">@if ($r['status'])<span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $r['status']->color() }}">{{ $r['status']->label() }}</span>@endif</td>
                                <td class="{{ $td }} text-right tabular-nums font-semibold">{{ $r['clients'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $r['lifetime'] }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['first_at']->format('d M H:i') }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['last_at']->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No items used on clients in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data['rows']->count() > 200)<p class="mt-3 text-xs text-gray-500">{{ __('Showing the latest 200 of :n. Download Excel for all.', ['n' => $data['rows']->count()]) }}</p>@endif
        </x-dash.card>

    @elseif ($tab === 'corrections')
        {{-- ============================== CORRECTIONS ============================== --}}
        <x-dash.card :title="__('Corrections')" :subtitle="__('Deliveries undone and dispatches cancelled, with who made them and why')">
            <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                        @foreach ([__('Date'), __('Correction'), __('Reference'), __('Location'), __('Units'), __('By'), __('Reason')] as $h)
                            <th class="{{ $th }} {{ $loop->index === 4 ? 'text-right' : '' }}">{{ $h }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['rows'] as $r)
                            <tr>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['date']->format('d M Y H:i') }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['type'] }}</td>
                                <td class="{{ $td }} font-mono whitespace-nowrap text-xs">{{ $r['reference'] }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $r['location'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $r['units'] }}</td>
                                <td class="{{ $td }}">{{ $r['by'] }}</td>
                                <td class="{{ $td }}">{{ $r['reason'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No corrections in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-dash.card>

    @elseif ($tab === 'compare')
        {{-- ============================== BRANCH COMPARISON ============================== --}}
        {{-- Inline @php() only: a block @php here would be merged with the inline ones above by Blade. --}}
        @php($cmpRows = $data['rows'])
        @php($cmpLocs = $data['locations'])
        @php($short = fn ($n) => \Illuminate\Support\Str::before($n, ' Branch'))
        @php($chartRow = $cmpRows->firstWhere('product_id', $compareProduct) ?? $cmpRows->first(fn ($r) => $r['average'] !== null))
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Customers served per item (per fill for refillable items), for items used up in the period. A branch far below the average uses more product per customer: check for wastage, over-application or missing customer scans.') }}</p>

        @if ($cmpRows->isEmpty())
            <div class="panel py-12 text-center text-sm text-gray-500">{{ __('No usage recorded in this period.') }}</div>
        @else
            @if ($chartRow && $chartRow['average'] !== null)
                <x-dash.card :title="__('Customers per item by branch')" :subtitle="__('Average across branches: :avg', ['avg' => number_format($chartRow['average'], 1)])">
                    <x-slot:action>
                        <select wire:model.live="compareProduct" class="form-field py-1.5 text-sm">
                            @foreach ($cmpRows->filter(fn ($r) => $r['average'] !== null) as $r)
                                <option value="{{ $r['product_id'] }}" @selected($r['product_id'] === $chartRow['product_id'])>{{ $r['product'] }}</option>
                            @endforeach
                        </select>
                    </x-slot:action>
                    @php($chartCells = $chartRow['cells']->filter(fn ($c) => $c['per_fill'] !== null))
                    <div wire:ignore wire:key="cmp-{{ $chartRow['product_id'] }}-{{ md5(json_encode($chartCells)) }}"
                        x-data="vizChart('bar', @js(['name' => __('Customers per item'), 'data' => $chartCells->pluck('per_fill')->values()->all(), 'labels' => $chartCells->keys()->map(fn ($id) => $short($cmpLocs->firstWhere('id', $id)?->name))->all(), 'color' => 'aqua']))"></div>
                </x-dash.card>
            @endif

            <x-dash.card :title="__('All products')" :subtitle="__('Green: well above the average. Red: well below (15% or more).')">
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Product') }}</th>
                            @foreach ($cmpLocs as $w)
                                <th class="{{ $th }} text-right">{{ $short($w->name) }}</th>
                            @endforeach
                            <th class="{{ $th }} text-right">{{ __('Average') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Extra cost / customer') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($cmpRows as $r)
                                <tr>
                                    <td class="{{ $td }}"><button type="button" wire:click="$set('compareProduct', '{{ $r['product_id'] }}')" class="text-left hover:text-primary-600">{{ $r['product'] }}</button></td>
                                    @foreach ($cmpLocs as $w)
                                        @php($c = $r['cells'][$w->id])
                                        <td @class([$td, 'text-right tabular-nums whitespace-nowrap',
                                            'bg-[#1baf7a]/10' => ($c['vs_average'] ?? 0) >= 15,
                                            'bg-[#d03b3b]/10' => ($c['vs_average'] ?? 0) <= -15])>
                                            @if ($c['per_fill'] !== null)
                                                <span class="font-semibold">{{ number_format($c['per_fill'], 1) }}</span>
                                                @if ($c['vs_average'] !== null && $c['vs_average'] !== 0)
                                                    <span @class(['text-xs', 'text-[#13805a]' => $c['vs_average'] > 0, 'text-[#b02f2f]' => $c['vs_average'] < 0])>{{ $c['vs_average'] > 0 ? '+' : '' }}{{ $c['vs_average'] }}%</span>
                                                @endif
                                                <span class="block text-[11px] text-gray-500">{{ trans_choice(':n item|:n items', $c['fills'], ['n' => $c['fills']]) }} · {{ trans_choice(':n customer|:n customers', $c['customers'], ['n' => $c['customers']]) }}</span>
                                            @elseif ($c['customers'])
                                                <span class="text-xs text-gray-500">{{ trans_choice(':n customer|:n customers', $c['customers'], ['n' => $c['customers']]) }}</span>
                                                <span class="block text-[11px] text-gray-400">{{ __('none used up yet') }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="{{ $td }} text-right tabular-nums font-semibold">{{ $r['average'] === null ? '—' : number_format($r['average'], 1) }}</td>
                                    <td class="{{ $td }} text-right tabular-nums whitespace-nowrap">
                                        @if ($r['cost_gap'])
                                            {{ $ugx($r['cost_gap']) }}
                                            <span class="block text-[11px] text-gray-500">{{ $short($cmpLocs->firstWhere('id', $r['worst'])?->name) }} {{ __('vs') }} {{ $short($cmpLocs->firstWhere('id', $r['best'])?->name) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ __('Extra cost / customer: how much more product cost each customer uses at the lowest branch than at the best branch.') }}</p>
            </x-dash.card>
        @endif

    @elseif ($tab === 'value')
        {{-- ============================== STOCK VALUE ============================== --}}
        @php($t = $data['totals'])
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ([
                [__('Units in stock'), number_format($t['units']), 'boxes', 'bg-[#1baf7a]/12 text-[#0f8a5f] dark:text-[#5fd3a8]'],
                [__('Value at cost'), $ugxShort($t['cost']), 'coins', 'bg-[#6250d6]/12 text-[#4f3fc0] dark:text-[#b3abf2]'],
                [__('Value at selling price'), $ugxShort($t['price']), 'tags', 'bg-[#2a78d6]/12 text-[#1f62b3] dark:text-[#8db8f0]'],
                [__('In transit'), number_format($data['inTransit']['units']) . ' · ' . $ugxShort($data['inTransit']['cost']), 'truck', 'bg-[#eb6834]/12 text-[#c24e1d] dark:text-[#f2a07e]'],
            ] as [$label, $value, $icon, $chip])
                <div class="panel p-4 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ $label }}</div>
                        <div class="mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white truncate" title="{{ $value }}">{{ $value }}</div>
                    </div>
                    <span class="w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $chip }}"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <x-dash.card :title="__('By location')" :subtitle="__('Sealed and in-use units – stock counts until an item is used up')">
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Location') }}</th><th class="{{ $th }} text-right">{{ __('Units') }}</th><th class="{{ $th }} text-right">{{ __('In use') }}</th><th class="{{ $th }} text-right">{{ __('Cost') }}</th><th class="{{ $th }} text-right">{{ __('Selling') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($data['byLocation'] as $r)
                                <tr><td class="{{ $td }}">{{ $r['location'] }}</td><td class="{{ $td }} text-right tabular-nums">{{ number_format($r['units']) }}</td><td class="{{ $td }} text-right tabular-nums text-gray-500">{{ number_format($r['in_use']) }}</td><td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($r['cost']) }}</td><td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($r['price']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold"><tr>
                            <td class="{{ $td }}">{{ __('Total') }}</td><td class="{{ $td }} text-right tabular-nums">{{ number_format($t['units']) }}</td><td class="{{ $td }} text-right tabular-nums text-gray-500">{{ number_format($t['in_use']) }}</td><td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($t['cost']) }}</td><td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($t['price']) }}</td>
                        </tr></tfoot>
                    </table>
                </div>
            </x-dash.card>

            <x-dash.card :title="__('By product')" :subtitle="__('Highest value first')">
                <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                            <th class="{{ $th }}">{{ __('Product') }}</th><th class="{{ $th }} text-right">{{ __('Units') }}</th><th class="{{ $th }} text-right">{{ __('In use') }}</th><th class="{{ $th }} text-right">{{ __('Cost') }}</th><th class="{{ $th }} text-right">{{ __('Selling') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($data['byProduct'] as $r)
                                <tr><td class="{{ $td }}">{{ $r['product'] }}</td><td class="{{ $td }} text-right tabular-nums">{{ number_format($r['units']) }}</td><td class="{{ $td }} text-right tabular-nums text-gray-500">{{ number_format($r['in_use']) }}</td><td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($r['cost']) }}</td><td class="{{ $td }} text-right tabular-nums whitespace-nowrap">{{ $ugx($r['price']) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No stock at these locations.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dash.card>
        </div>

    @else
        {{-- ============================== MOVEMENTS ============================== --}}
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="$set('action', '')" @class(['px-3 py-1.5 rounded-full text-xs font-semibold border', 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900' => $action === '', 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' => $action !== ''])>
                {{ __('All') }} · {{ number_format($data['summary']->sum('count')) }}
            </button>
            @foreach ($data['summary'] as $s)
                <button type="button" wire:click="$set('action', '{{ $s['action']->value }}')" @class(['px-3 py-1.5 rounded-full text-xs font-semibold', $s['action']->color(), 'ring-2 ring-primary-500' => $action === $s['action']->value])>
                    {{ $s['action']->label() }} · {{ number_format($s['count']) }}
                </button>
            @endforeach
        </div>

        <x-dash.card>
            <div class="-mx-4 sm:-mx-5 -mt-4 sm:-mt-5 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-gray-100 dark:border-gray-800"><tr>
                        @foreach ([__('When'), __('Unit'), __('Product'), __('Box'), __('Action'), __('Location'), __('By'), __('Transfer')] as $h)
                            <th class="{{ $th }}">{{ $h }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($data['scans'] as $scan)
                            <tr wire:key="mv-{{ $scan->id }}">
                                <td class="{{ $td }} whitespace-nowrap">{{ $scan->created_at->format('d M Y H:i') }}</td>
                                <td class="{{ $td }}"><a href="{{ route('tracking.lookup', ['code' => $scan->code]) }}" wire:navigate class="font-mono whitespace-nowrap text-primary-600 hover:underline">{{ $scan->code }}</a></td>
                                <td class="{{ $td }}">{{ $scan->product?->name }}</td>
                                <td class="{{ $td }} font-mono whitespace-nowrap text-xs">{{ $scan->box?->code ?? '—' }}</td>
                                <td class="{{ $td }}"><span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $scan->action->color() }}">{{ $scan->action->label() }}</span></td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $scan->warehouse?->name }}</td>
                                <td class="{{ $td }}">{{ $scan->user?->name }}</td>
                                <td class="{{ $td }} font-mono whitespace-nowrap text-xs">{{ $scan->transfer?->reference }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No movements in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $data['scans']->links() }}</div>
        </x-dash.card>
    @endif
    </div>
</div>
