<?php

declare(strict_types=1);

use App\Enums\TransferStatus;
use App\Livewire\Tracking\Overview;
use App\Models\ProductWarehouse;
use App\Models\Transfer;
use App\Support\Branches;
use App\Traits\WithAlert;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use WithAlert;

    public int $howMany = 5;

    /** Units marked missing when a transfer was received. */
    #[Computed]
    public function missing()
    {
        return Overview::missingItems()->with('product:id,name', 'scans.warehouse:id,name')
            ->latest('updated_at')->limit(5)->get();
    }

    #[Computed]
    public function missingCount(): int
    {
        return Overview::missingItems()->count();
    }

    /** Transfers still not scanned in a day after dispatch. */
    #[Computed]
    public function overdue()
    {
        // A branch admin is chased for transfers they have to scan in.
        return Branches::scope(Transfer::query(), 'to_warehouse_id')->with('toWarehouse:id,name')
            ->where('status', TransferStatus::IN_TRANSIT)
            ->where('dispatched_at', '<', now()->subDay())
            ->oldest('dispatched_at')->get();
    }

    /** Low stock grouped by location; only rows with a re-order level set count. */
    #[Computed]
    public function lowStock()
    {
        return Branches::scope(ProductWarehouse::query())
            ->with('product:id,name', 'warehouse:id,name')
            ->whereHas('product')
            ->where('stock_alert', '>', 0)
            ->whereColumn('qty', '<=', 'stock_alert')
            ->orderBy('qty')
            ->get()
            ->groupBy('warehouse_id');
    }

    #[Computed]
    public function unread()
    {
        return Auth::user()->unreadNotifications()->take($this->howMany)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /** The badge counts what needs action; low stock is shown inside but not counted. */
    #[Computed]
    public function badge(): int
    {
        return $this->missingCount + $this->overdue->count() + $this->unreadCount;
    }

    public function loadMore(): void
    {
        $this->howMany += 5;
    }

    public function markAsRead(string $id): void
    {
        Auth::user()->unreadNotifications()->findOrFail($id)->markAsRead();
    }

    public function readAll(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->to($notification->data['route'] ?? route('notifications.manager'));
    }
};
?>

<div x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" @click="open = true" title="{{ __('Notifications') }}"
        class="relative w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:text-primary-600 hover:bg-white dark:hover:bg-gray-800 transition">
        <i class="fas fa-bell" aria-hidden="true"></i>
        <span class="sr-only">{{ __('Notifications') }}</span>
        @if ($this->badge > 0)
            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 grid place-items-center rounded-full bg-[#d03b3b] text-white text-[10px] font-bold ring-2 ring-canvas dark:ring-gray-950">
                {{ $this->badge > 99 ? '99+' : $this->badge }}
            </span>
        @endif
    </button>

    {{-- Teleported to <body>: the top bar's backdrop blur would otherwise trap a fixed drawer inside it. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[100]" role="dialog" aria-modal="true" aria-labelledby="notif-title">
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-gray-950/40 backdrop-blur-[2px]" @click="open = false"></div>

            <aside x-show="open"
                x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                class="absolute inset-y-0 right-0 w-full sm:max-w-md flex flex-col bg-canvas dark:bg-gray-950 shadow-2xl">

                {{-- Header --}}
                <header class="flex items-center justify-between gap-3 px-5 h-16 shrink-0 bg-white dark:bg-gray-900 border-b border-gray-200/70 dark:border-gray-800">
                    <div>
                        <h2 id="notif-title" class="text-base font-bold text-gray-900 dark:text-white">{{ __('Notifications') }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->badge ? trans_choice(':n item needs attention|:n items need attention', $this->badge, ['n' => $this->badge]) : __('You are all caught up') }}
                        </p>
                    </div>
                    <button type="button" @click="open = false" class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="{{ __('Close') }}">
                        <i class="fas fa-times"></i>
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto p-4 space-y-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
                    {{-- Missing items --}}
                    @if ($this->missingCount)
                        <section class="panel overflow-hidden">
                            <div class="flex items-center justify-between px-4 pt-3 pb-2">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                    <span class="w-6 h-6 rounded-md grid place-items-center bg-[#d03b3b] text-white text-[11px]"><i class="fas fa-exclamation-triangle"></i></span>
                                    {{ __('Missing items') }}
                                </h3>
                                <span class="text-xs font-semibold text-[#b02f2f] dark:text-[#f08a8a]">{{ $this->missingCount }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($this->missing as $item)
                                    @php($last = $item->scans->last())
                                    <li>
                                        <a href="{{ route('tracking.lookup', ['code' => $item->code]) }}" wire:navigate @click="open = false" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            <span class="font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ $item->code }}</span>
                                            <span class="flex-1 min-w-0">
                                                <span class="block truncate text-sm text-gray-700 dark:text-gray-300">{{ $item->product?->name }}</span>
                                                <span class="block text-xs text-gray-500">{{ __('Not received at :where', ['where' => $last?->warehouse?->name ?? '—']) }} · {{ $item->updated_at->diffForHumans(short: true) }}</span>
                                            </span>
                                            <i class="fas fa-chevron-right text-[10px] text-gray-400"></i>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Overdue transfers --}}
                    @if ($this->overdue->isNotEmpty())
                        <section class="panel overflow-hidden">
                            <div class="flex items-center justify-between px-4 pt-3 pb-2">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                    <span class="w-6 h-6 rounded-md grid place-items-center bg-[#2a78d6]/15 text-[#1f62b3] dark:text-[#8db8f0] text-[11px]"><i class="fas fa-truck"></i></span>
                                    {{ __('Not received after 24 h') }}
                                </h3>
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $this->overdue->count() }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($this->overdue as $t)
                                    <li>
                                        <a href="{{ route('tracking.receive', $t) }}" wire:navigate @click="open = false" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            <span class="flex-1 min-w-0">
                                                <span class="block truncate text-sm text-gray-800 dark:text-gray-200">{{ $t->toWarehouse?->name }}</span>
                                                <span class="block text-xs text-gray-500"><span class="font-mono">{{ $t->reference }}</span> · {{ $t->total_qty }} {{ __('units') }} · {{ __('sent') }} {{ $t->dispatched_at?->diffForHumans() }}</span>
                                            </span>
                                            <i class="fas fa-chevron-right text-[10px] text-gray-400"></i>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Low stock by location --}}
                    @if ($this->lowStock->isNotEmpty())
                        <section class="panel overflow-hidden" x-data="{ more: false }">
                            <div class="flex items-center justify-between px-4 pt-3 pb-2">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                    <span class="w-6 h-6 rounded-md grid place-items-center bg-[#eb6834]/15 text-[#c24e1d] dark:text-[#f2a07e] text-[11px]"><i class="fas fa-arrow-down"></i></span>
                                    {{ __('Low stock') }}
                                </h3>
                                <span class="text-xs text-gray-500">{{ trans_choice(':n location|:n locations', $this->lowStock->count(), ['n' => $this->lowStock->count()]) }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($this->lowStock as $rows)
                                    <li class="px-4 py-2.5" @if ($loop->index >= 4) x-show="more" x-cloak @endif>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $rows->first()->warehouse?->name }}</span>
                                            <span class="text-xs text-gray-500">{{ trans_choice(':n product low|:n products low', $rows->count(), ['n' => $rows->count()]) }}</span>
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach ($rows->take(3) as $row)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300">
                                                    {{ \Illuminate\Support\Str::limit($row->product?->name, 22) }}
                                                    <b class="tabular-nums {{ $row->qty == 0 ? 'text-[#b02f2f] dark:text-[#f08a8a]' : 'text-gray-900 dark:text-white' }}">{{ $row->qty }}</b>
                                                </span>
                                            @endforeach
                                            @if ($rows->count() > 3)
                                                <span class="px-1.5 py-0.5 text-xs text-gray-500">+{{ $rows->count() - 3 }}</span>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($this->lowStock->count() > 4)
                                <button type="button" @click="more = !more" class="w-full px-4 py-2 text-xs font-semibold text-primary-600 dark:text-primary-400 border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                    x-text="more ? '{{ __('Show fewer') }}' : '{{ __('Show all :n locations', ['n' => $this->lowStock->count()]) }}'"></button>
                            @endif
                        </section>
                    @endif

                    {{-- System messages --}}
                    <section class="panel overflow-hidden">
                        <div class="flex items-center justify-between px-4 pt-3 pb-2">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                <span class="w-6 h-6 rounded-md grid place-items-center bg-primary-50 dark:bg-primary-900/40 text-primary-600 dark:text-primary-300 text-[11px]"><i class="fas fa-inbox"></i></span>
                                {{ __('Messages') }}
                            </h3>
                            @if ($this->unreadCount)
                                <button type="button" wire:click="readAll" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">{{ __('Mark all read') }}</button>
                            @endif
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($this->unread as $n)
                                <li wire:key="n-{{ $n->id }}" class="group flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/60 cursor-pointer" wire:click="open('{{ $n->id }}')">
                                    <span class="mt-1.5 w-2 h-2 rounded-full bg-primary-600 shrink-0"></span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $n->data['title'] ?? __('Notification') }}</span>
                                        <span class="block text-xs text-gray-600 dark:text-gray-400 line-clamp-2">{{ $n->data['message'] ?? '' }}</span>
                                        <span class="block mt-0.5 text-[11px] text-gray-400">{{ $n->created_at->diffForHumans() }}</span>
                                    </span>
                                    <button type="button" wire:click.stop="markAsRead('{{ $n->id }}')" class="opacity-0 group-hover:opacity-100 text-xs text-gray-400 hover:text-primary-600" title="{{ __('Mark read') }}"><i class="fas fa-check"></i></button>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('No new messages') }}</li>
                            @endforelse
                        </ul>
                        @if ($this->unreadCount > $howMany)
                            <button type="button" wire:click="loadMore" class="w-full px-4 py-2 text-xs font-semibold text-primary-600 border-t border-gray-100 dark:border-gray-800">{{ __('Load more') }}</button>
                        @endif
                    </section>

                    @if (! $this->badge && $this->lowStock->isEmpty())
                        <div class="text-center py-6">
                            <span class="mx-auto w-12 h-12 rounded-full grid place-items-center bg-[#1baf7a]/15 text-[#0f8a5f] dark:text-[#5fd3a8]"><i class="fas fa-check"></i></span>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ __('Nothing needs your attention right now.') }}</p>
                        </div>
                    @endif
                </div>

                <footer class="shrink-0 px-4 py-3 bg-white dark:bg-gray-900 border-t border-gray-200/70 dark:border-gray-800 flex gap-2">
                    <a href="{{ route('tracking.lookup') }}" wire:navigate @click="open = false" class="btn-secondary flex-1 py-2"><i class="fas fa-map-marker-alt text-primary-600"></i>{{ __('Item lookup') }}</a>
                    <a href="{{ route('notifications.manager') }}" wire:navigate @click="open = false" class="btn-secondary flex-1 py-2"><i class="fas fa-sliders-h text-primary-600"></i>{{ __('Settings') }}</a>
                </footer>
            </aside>
        </div>
    </template>
</div>
