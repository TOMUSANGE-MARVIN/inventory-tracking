<div>
    <x-page-container title="{{ __('Scan station') }}" :breadcrumbs="$worker ? [] : [['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Scan station')]]">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="panel p-4 space-y-3">
                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ __('What are you scanning for?') }}</div>
                    <div @class(['grid gap-2', 'grid-cols-2' => count($modes) === 2, 'grid-cols-1 sm:grid-cols-3' => count($modes) === 3])>
                        @foreach ($modes as $value => [$icon, $label, $color])
                            <button type="button" wire:click="$set('action', '{{ $value }}')" @class([
                                'px-3 py-3 rounded-lg text-sm font-medium border-2 transition inline-flex items-center justify-center gap-2',
                                'border-primary-600 ' . $color => $action === $value,
                                'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' => $action !== $value,
                            ])><i class="fas fa-{{ $icon }}"></i>{{ $label }}</button>
                        @endforeach
                    </div>
                    @if ($action === 'served_client')
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-teal-50 dark:bg-teal-900/20 p-3">
                            <div class="text-sm text-teal-900 dark:text-teal-200">
                                <div class="font-medium">{{ __('Scan the item each time it is used on a client') }}</div>
                                <div class="text-xs opacity-80">{{ __('Sealed items are opened automatically on their first client.') }}</div>
                            </div>
                            <div class="flex items-center gap-2" aria-label="{{ __('Clients in this scan') }}">
                                <button type="button" wire:click="$set('clients', {{ max(1, $clients - 1) }})" class="w-9 h-9 rounded-lg border border-teal-300 bg-white dark:bg-gray-900 text-teal-800 dark:text-teal-200" aria-label="{{ __('Fewer clients') }}"><i class="fas fa-minus text-xs"></i></button>
                                <div class="text-center min-w-[3.5rem]"><div class="text-xl font-bold text-teal-900 dark:text-teal-100">{{ $clients }}</div><div class="text-[11px] text-teal-800/80 dark:text-teal-300">{{ trans_choice('client|clients', $clients) }}</div></div>
                                <button type="button" wire:click="$set('clients', {{ min(50, $clients + 1) }})" class="w-9 h-9 rounded-lg border border-teal-300 bg-white dark:bg-gray-900 text-teal-800 dark:text-teal-200" aria-label="{{ __('More clients') }}"><i class="fas fa-plus text-xs"></i></button>
                            </div>
                        </div>
                    @elseif ($action === 'done')
                        <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-sm text-gray-800 dark:text-gray-200">
                            <div class="font-medium">{{ __('Scan an item that is used up') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Refillable items go to “Needs refill” and keep their barcode. Others are marked disposed.') }}</div>
                        </div>
                    @endif
                    <p class="text-xs text-gray-500">{{ __('The item\'s label tells the system which branch it is at.') }}</p>
                </div>

                <x-tracking.scanner id="station-reader" />
                <x-tracking.flash :flash="$flash" />
                @if ($lastItem && $action === 'served_client')
                    <div class="flex flex-wrap gap-2">
                        @if ($lastItem->status === \App\Enums\ItemStatus::IN_USE)
                            <button type="button" wire:click="finishLast" wire:confirm="{{ __('Mark :code as used up?', ['code' => $lastItem->code]) }}" class="btn-primary py-2 text-sm flex-1 justify-center">
                                <i class="fas fa-flag-checkered"></i>{{ $lastItem->product?->is_refillable ? __('Mark :code done – needs refill', ['code' => $lastItem->code]) : __('Mark :code done (used up)', ['code' => $lastItem->code]) }}
                            </button>
                        @endif
                        <button type="button" wire:click="undo" class="btn-secondary py-2 text-sm"><i class="fas fa-undo"></i>{{ __('Undo last scan') }}</button>
                    </div>
                @endif
            </div>

            <div class="panel p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $worker ? __('My recent scans') : __('Recent scans') }}</h3>
                    <span class="text-xs px-2 py-1 rounded-full bg-teal-100 text-teal-800"><i class="fas fa-user-check mr-1"></i>{{ trans_choice(':n client served today|:n clients served today', $servedToday, ['n' => $servedToday]) }}</span>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($recent as $scan)
                        <li wire:key="scan-{{ $scan->id }}" class="py-2 flex items-center justify-between gap-2">
                            <div>
                                <div class="text-gray-900 dark:text-gray-100"><span class="font-mono">{{ $scan->code }}</span> · {{ $scan->product?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $scan->warehouse?->name }} · {{ $scan->created_at->format('d M H:i') }} · {{ $scan->user?->name }}@if ($scan->station) · {{ $scan->station }}@endif</div>
                            </div>
                            <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $scan->action->color() }}">{{ $scan->action->label() }}@if ($scan->action === \App\Enums\ScanAction::SERVED_CLIENT && $scan->quantity > 1) ×{{ $scan->quantity }}@endif</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-gray-500">{{ __('No scans yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-page-container>
</div>
