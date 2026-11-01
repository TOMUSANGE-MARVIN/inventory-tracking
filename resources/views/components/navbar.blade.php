@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
@endphp

<nav aria-label="secondary"
    class="sticky top-0 z-20 flex items-center justify-between gap-3 px-4 sm:px-6 lg:px-8 h-16 bg-canvas/85 dark:bg-gray-950/85 backdrop-blur border-b border-gray-200/70 dark:border-gray-800 transition-transform duration-500"
    :class="{ '-translate-y-full': scrollingDown, 'translate-y-0': scrollingUp }"
    x-cloak>

    <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="isSidebarOpen = !isSidebarOpen"
            class="p-2 rounded-lg text-gray-500 hover:text-primary-600 hover:bg-white dark:hover:bg-gray-800 transition">
            <span class="sr-only">{{ __('Toggle menu') }}</span>
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="min-w-0 hidden sm:block">
            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $greeting }}, {{ Auth::user()->name }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ now()->format('l, j F Y') }}</div>
        </div>
    </div>

    <div class="flex items-center gap-1.5 sm:gap-2">
        {{-- Quick lookup: type or scan an item label to see where it is. --}}
        <form action="{{ route('tracking.lookup') }}" method="GET" class="hidden md:block">
            <label class="relative block">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                <input type="search" name="code" placeholder="{{ __('Find item, e.g. SP-000123') }}"
                    class="w-64 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-8 pr-3 py-2 text-sm text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none">
            </label>
        </form>

        <button type="button" @click="toggleTheme()" title="{{ __('Switch light / dark') }}"
            class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:text-primary-600 hover:bg-white dark:hover:bg-gray-800 transition">
            <i x-show="!isDarkMode" class="fas fa-moon"></i>
            <i x-show="isDarkMode" class="fas fa-sun"></i>
        </button>

        @can('sale_access')
            <a href="{{ route('pos.index') }}" wire:navigate title="{{ __('Point of sale') }}"
                class="inline-flex items-center gap-2 h-9 px-2.5 sm:px-3.5 rounded-lg bg-primary-600 text-white text-sm font-semibold shadow-sm shadow-primary-600/30 hover:bg-primary-700 transition">
                <i class="fas fa-cash-register"></i><span class="hidden sm:inline">{{ __('POS') }}</span>
            </a>
        @endcan

        @can('show_notifications')
            <div class="flex items-center">
                <livewire:notification-bell />
            </div>
        @endcan

        <x-dropdown align="right" width="56">
            <x-slot name="trigger">
                <button type="button" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-white dark:hover:bg-gray-800 transition focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                    <span class="w-8 h-8 rounded-full bg-primary-600 text-white grid place-items-center text-sm font-semibold">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                    <span class="hidden md:block text-sm font-medium text-gray-700 dark:text-gray-200">{{ Auth::user()->name }}</span>
                    <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-800">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ Auth::user()->name }}</div>
                    <div class="text-xs text-gray-500 truncate">
                        {{ \App\Support\Branches::isSuperAdmin() ? __('Super admin · all locations') : __('Branch admin · :where', ['where' => \App\Support\Branches::warehouses()->pluck('name')->join(', ')]) }}
                    </div>
                </div>
                <x-dropdown-link :href="route('profile.index')" wire:navigate class="text-sm text-gray-700 dark:text-gray-300">
                    <i class="fas fa-user w-4 mr-2 text-primary-500"></i>{{ __('Profile') }}
                </x-dropdown-link>
                @can('setting_access')
                    <x-dropdown-link :href="route('settings.index')" wire:navigate class="text-sm text-gray-700 dark:text-gray-300">
                        <i class="fas fa-cog w-4 mr-2 text-primary-500"></i>{{ __('Settings') }}
                    </x-dropdown-link>
                @endcan
                @role('admin')
                    <x-dropdown-link class="text-sm text-gray-700 dark:text-gray-300">
                        <livewire:cache-clear />
                    </x-dropdown-link>
                @endrole
                <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>
                <livewire:layout.navigation />
            </x-slot>
        </x-dropdown>
    </div>
</nav>
