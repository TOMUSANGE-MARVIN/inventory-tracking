@php
    $th = 'px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap';
    $td = 'px-3 py-2 text-sm text-gray-800 dark:text-gray-200';
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('Stock-take') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Scan everything physically at a location and compare it with what the system expects.') }}</p>
        </div>
        @if ($take)
            <button type="button" wire:click="close" class="btn-secondary py-2"><i class="fas fa-arrow-left"></i>{{ __('All stock-takes') }}</button>
        @endif
    </div>

    <x-tracking.flash :flash="$flash" />

    @if (! $take)
        {{-- Start, continue, history --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <div class="xl:col-span-5 panel p-4 space-y-3">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('Start a stock-take') }}</h2>
                <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ __('Location to count') }}</span>
                    <select wire:model="warehouse" class="form-field w-full mt-1">
                        @foreach ($locations as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </label>
                <ol class="text-xs text-gray-600 dark:text-gray-400 list-decimal ml-4 space-y-1">
                    <li>{{ __('Scan every item on the shelves, in use at stations, and empty containers waiting for refill.') }}</li>
                    <li>{{ __('A sealed box can be counted with its QR code.') }}</li>
                    <li>{{ __('Finish to see what was not found, and what was found but not expected here.') }}</li>
                </ol>
                <button type="button" wire:click="start" class="btn-primary w-full"><i class="fas fa-clipboard-check"></i>{{ __('Start stock-take') }}</button>
            </div>

            <div class="xl:col-span-7 space-y-4">
                @if ($open->isNotEmpty())
                    <x-dash.card :title="__('In progress')">
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 -my-2">
                            @foreach ($open as $o)
                                <li class="py-2 flex items-center justify-between gap-2 text-sm">
                                    <span><span class="font-mono">{{ $o->reference }}</span> · {{ $o->warehouse?->name }} · {{ __('started :when by :name', ['when' => $o->created_at->diffForHumans(), 'name' => $o->starter?->name]) }}</span>
                                    <button type="button" wire:click="$set('takeId', {{ $o->id }})" class="btn-primary px-3 py-1.5 text-xs">{{ __('Continue') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </x-dash.card>
                @endif

                <x-dash.card :title="__('Past stock-takes')">
                    <div class="-mx-4 sm:-mx-5 overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-y border-gray-100 dark:border-gray-800"><tr>
                                <th class="{{ $th }}">{{ __('Reference') }}</th><th class="{{ $th }}">{{ __('Location') }}</th><th class="{{ $th }}">{{ __('Finished') }}</th>
                                <th class="{{ $th }} text-right">{{ __('Found') }}</th><th class="{{ $th }} text-right">{{ __('Not found') }}</th><th class="{{ $th }} text-right">{{ __('Unexpected') }}</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($history as $h)
                                    <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50" wire:click="$set('takeId', {{ $h->id }})">
                                        <td class="{{ $td }} font-mono text-primary-600">{{ $h->reference }}</td>
                                        <td class="{{ $td }}">{{ $h->warehouse?->name }}</td>
                                        <td class="{{ $td }} whitespace-nowrap">{{ $h->finished_at?->format('d M Y H:i') }}<span class="block text-xs text-gray-500">{{ $h->finisher?->name }}</span></td>
                                        <td class="{{ $td }} text-right tabular-nums">{{ $h->found }} / {{ $h->expected }}</td>
                                        <td class="{{ $td }} text-right tabular-nums {{ $h->not_found ? 'font-semibold text-[#b02f2f]' : '' }}">{{ $h->not_found }}</td>
                                        <td class="{{ $td }} text-right tabular-nums {{ $h->unexpected ? 'font-semibold text-[#a06d00]' : '' }}">{{ $h->unexpected }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="{{ $td }} text-center text-gray-500 py-8">{{ __('No stock-takes yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-dash.card>
            </div>
        </div>
    @else
        @php
            $isOpen = $take->status === \App\Models\StockTake::OPEN;
            $foundCount = $found->count();
            $expectedCount = $isOpen ? $foundCount + $pending->count() : $take->expected;
            $pct = $expectedCount ? (int) round($foundCount / $expectedCount * 100) : 100;
        @endphp
        <div class="panel p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $take->reference }}</span>
                    <span @class(['px-2 py-0.5 rounded-full text-xs font-medium', 'bg-amber-100 text-amber-800' => $isOpen, 'bg-green-100 text-green-800' => ! $isOpen])>{{ $isOpen ? __('In progress') : __('Finished') }}</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">{{ $take->warehouse?->name }} · {{ __('started :date by :name', ['date' => $take->created_at->format('d M Y H:i'), 'name' => $take->starter?->name]) }}@if ($take->finished_at) · {{ __('finished :date by :name', ['date' => $take->finished_at->format('d M Y H:i'), 'name' => $take->finisher?->name]) }}@endif</div>
            </div>
            <div class="min-w-56">
                <div class="flex justify-between text-sm"><span class="text-gray-600 dark:text-gray-300">{{ __('Counted') }}</span><span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ $foundCount }} / {{ $expectedCount }}</span></div>
                <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden"><div class="h-full bg-[#1baf7a]" style="width: {{ $pct }}%"></div></div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <div class="xl:col-span-5 space-y-4">
                @if ($isOpen)
                    <x-tracking.scanner id="stocktake-reader" />

                    <div class="panel p-4 space-y-3">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Finish the count') }}</h3>
                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="markMissing" class="mt-0.5 rounded border-gray-300 text-primary-600">
                            <span>{{ trans_choice('Mark the :n item not counted as missing (takes it off the stock count)|Mark the :n items not counted as missing (takes them off the stock count)', $pending->count(), ['n' => $pending->count()]) }}</span>
                        </label>
                        <input type="text" wire:model="note" placeholder="{{ __('Note (optional)') }}" class="form-field w-full">
                        <button type="button" wire:click="finish" wire:confirm="{{ __('Finish this stock-take? Items not counted will be listed as not found.') }}" class="btn-primary w-full"><i class="fas fa-flag-checkered"></i>{{ __('Finish stock-take') }}</button>
                        <button type="button" wire:click="cancel" wire:confirm="{{ __('Cancel this stock-take? Nothing will be changed.') }}" class="w-full text-sm text-red-600 hover:underline">{{ __('Cancel stock-take') }}</button>
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ([[__('Found'), $take->found, 'text-[#13805a]'], [__('Not found'), $take->not_found, 'text-[#b02f2f]'], [__('Unexpected'), $take->unexpected, 'text-[#a06d00]']] as [$label, $value, $color])
                            <div class="panel p-4"><div class="text-xs text-gray-500">{{ $label }}</div><div class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</div></div>
                        @endforeach
                    </div>
                    @if ($take->note)<p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Note') }}: {{ $take->note }}</p>@endif
                @endif

                @if ($unexpected->isNotEmpty())
                    <x-dash.card :title="__('Found but not expected here')" :subtitle="__('Check these: the system has them somewhere else or in another state')">
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 -my-2 text-sm">
                            @foreach ($unexpected as $l)
                                <li class="py-2">
                                    <a href="{{ route('tracking.lookup', ['code' => $l->item?->code]) }}" wire:navigate class="font-mono text-primary-600 hover:underline">{{ $l->item?->code }}</a> · {{ $l->item?->product?->name }}
                                    <span class="block text-xs text-gray-500">{{ __('System said: :status at :where', ['status' => \App\Enums\ItemStatus::tryFrom((string) $l->system_status)?->label() ?? '—', 'where' => $l->systemWarehouse?->name ?? '—']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-dash.card>
                @endif
            </div>

            <div class="xl:col-span-7 space-y-4">
                @if ($isOpen)
                    <x-dash.card :title="__('Not counted yet')" :subtitle="__('Expected here but not scanned so far')">
                        @forelse ($pending->groupBy(fn ($i) => $i->product?->name) as $product => $items)
                            <div class="py-2 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $product }} <span class="text-gray-500 font-normal">· {{ $items->count() }}</span></div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($items as $i)
                                        <span class="font-mono text-[11px] px-1.5 py-0.5 rounded {{ $i->status->color() }}" title="{{ $i->status->label() }}">{{ $i->code }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-[#13805a]"><i class="fas fa-check-circle mr-1"></i>{{ __('Everything expected here has been counted.') }}</p>
                        @endforelse
                    </x-dash.card>
                @elseif ($notFound->isNotEmpty())
                    <x-dash.card :title="__('Not found')" :subtitle="__('Expected here but not counted')">
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 -my-2 text-sm">
                            @foreach ($notFound as $l)
                                <li class="py-2 flex items-center justify-between gap-2">
                                    <span><a href="{{ route('tracking.lookup', ['code' => $l->item?->code]) }}" wire:navigate class="font-mono text-primary-600 hover:underline">{{ $l->item?->code }}</a> · {{ $l->item?->product?->name }}</span>
                                    <span class="text-xs text-gray-500">{{ __('was :status', ['status' => mb_strtolower(\App\Enums\ItemStatus::tryFrom((string) $l->system_status)?->label() ?? '—')]) }} · {{ __('now :status', ['status' => mb_strtolower($l->item?->status->label() ?? '—')]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-dash.card>
                @endif

                <x-dash.card :title="__('Counted')" :subtitle="__('Latest first')">
                    <div class="flex flex-wrap gap-1">
                        @forelse ($found as $l)
                            <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-green-100 text-green-800" title="{{ $l->item?->product?->name }}">{{ $l->item?->code }}</span>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('Nothing counted yet.') }}</p>
                        @endforelse
                    </div>
                </x-dash.card>
            </div>
        </div>
    @endif
</div>
