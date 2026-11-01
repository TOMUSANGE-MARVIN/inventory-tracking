@props(['active' => false, 'title' => '', 'icon' => null])

<div x-data="{ open: @json((bool) $active) }">
    <x-sidebar.link collapsible title="{{ $title }}" @click="open = !open" :isActive="false" :icon="$icon"
        class="{{ $active ? '!text-gray-900 dark:!text-white' : '' }}" />

    <div class="mt-0.5 ml-5 pl-3 border-l border-gray-200 dark:border-gray-800 space-y-0.5"
        x-show="open && (isSidebarOpen || isSidebarHovered)" x-collapse>
        {{ $slot }}
    </div>
</div>
