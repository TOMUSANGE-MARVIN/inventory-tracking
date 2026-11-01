@props(['title'])

{{-- Group label; collapses to a hairline when the sidebar is icon-only. --}}
<div class="pt-4 pb-1 px-3">
    <span x-show="isSidebarOpen || isSidebarHovered" class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $title }}</span>
    <span x-show="!isSidebarOpen && !isSidebarHovered" class="block h-px bg-gray-200 dark:bg-gray-800"></span>
</div>
