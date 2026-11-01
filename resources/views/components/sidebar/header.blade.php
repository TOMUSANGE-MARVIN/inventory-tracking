<div class="flex items-center justify-between shrink-0 h-16 px-4">
    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 min-w-0">
        <span class="w-8 h-8 shrink-0 rounded-lg bg-primary-600 text-white grid place-items-center shadow-sm shadow-primary-600/30">
            <i class="fas fa-route text-sm"></i>
        </span>
        <span class="leading-none min-w-0" x-show="isSidebarOpen || isSidebarHovered">
            <span class="block text-base font-extrabold tracking-tight text-gray-900 dark:text-white truncate">{{ settings()->company_name ?? config('app.name') }}</span>
            <span class="block mt-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400 truncate">{{ \App\Support\Branches::isSuperAdmin() ? __('Head Office') : \App\Support\Branches::warehouses()->pluck('name')->join(', ') }}</span>
        </span>
    </a>

    <button type="button" x-on:click="isSidebarOpen = false" x-show="isSidebarOpen"
        class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
        <i class="fas fa-times"></i>
    </button>
</div>
