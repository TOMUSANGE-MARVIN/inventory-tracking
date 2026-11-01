<section class="sticky top-0 z-50 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200/70 dark:border-gray-800">
    <nav class="flex items-center justify-between gap-3 px-4 sm:px-6 h-16">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('dashboard') }}" wire:navigate title="{{ __('Back to dashboard') }}"
                class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="w-8 h-8 shrink-0 rounded-lg bg-primary-600 text-white grid place-items-center shadow-sm shadow-primary-600/30"><i class="fas fa-cash-register text-sm"></i></span>
            <div class="leading-none min-w-0">
                <div class="text-base font-extrabold tracking-tight text-gray-900 dark:text-white truncate">{{ __('Point of sale') }}</div>
                <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400 truncate">{{ settings()->company_name ?? config('app.name') }}</div>
            </div>
        </div>

        <div class="flex items-center gap-1.5 sm:gap-2">
            <div class="hidden md:flex"><x-button-fullscreen /></div>

            <button type="button" @click="toggleTheme()" title="{{ __('Switch light / dark') }}"
                class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                <i x-show="!isDarkMode" class="fas fa-moon"></i><i x-show="isDarkMode" class="fas fa-sun"></i>
            </button>

            @can('show_notifications')
                <livewire:notification-bell />
            @endcan

            <x-dropdown align="right" width="56">
                <x-slot name="trigger">
                    <button type="button" class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="sr-only">{{ __('POS menu') }}</span>
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('dashboard')" class="text-sm text-gray-700 dark:text-gray-300">
                        <i class="fas fa-th-large w-4 mr-2 text-primary-500"></i>{{ __('Dashboard') }}
                    </x-dropdown-link>
                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>
                    <x-dropdown-link wire:click="dispatchTo('products.create', 'createModal')" class="text-sm text-gray-700 dark:text-gray-300">
                        <i class="fas fa-box-open w-4 mr-2 text-primary-500"></i>{{ __('New product') }}
                    </x-dropdown-link>
                    <x-dropdown-link wire:click="dispatchTo('customers.create', 'createModal')" class="text-sm text-gray-700 dark:text-gray-300">
                        <i class="fas fa-user-plus w-4 mr-2 text-primary-500"></i>{{ __('New customer') }}
                    </x-dropdown-link>
                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>
                    <x-dropdown-link wire:click="dispatchTo('sales.recent', 'recentSales')" class="text-sm text-gray-700 dark:text-gray-300">
                        <i class="fas fa-history w-4 mr-2 text-primary-500"></i>{{ __('Recent sales') }}
                    </x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </div>
    </nav>
</section>
