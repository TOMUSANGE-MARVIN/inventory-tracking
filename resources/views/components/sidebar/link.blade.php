@props(['isActive' => false, 'title' => '', 'collapsible' => false, 'icon' => null])

@php
    $classes = 'flex items-center gap-3 py-2 px-3 rounded-lg text-sm font-medium transition-colors duration-150 '
        . ($isActive
            ? 'bg-primary-600 text-white shadow-sm shadow-primary-600/30'
            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white')
        . ($collapsible ? ' w-full' : '');
@endphp

@if ($collapsible)
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>
        <span class="shrink-0 w-5 text-center">{!! $icon ?? '<i class="fas fa-circle text-[6px]"></i>' !!}</span>
        <span class="flex-1 text-start whitespace-nowrap overflow-hidden text-ellipsis" x-show="isSidebarOpen || isSidebarHovered">{{ $title }}</span>
        <i class="fas fa-chevron-down text-[10px] opacity-60 transition-transform" :class="{ 'rotate-180': open }" x-show="isSidebarOpen || isSidebarHovered"></i>
    </button>
@else
    <a {{ $attributes->merge(['class' => $classes]) }} wire:navigate title="{{ $title }}">
        <span class="shrink-0 w-5 text-center">{!! $icon ?? '<i class="fas fa-circle text-[6px]"></i>' !!}</span>
        <span class="flex-1 whitespace-nowrap overflow-hidden text-ellipsis" x-show="isSidebarOpen || isSidebarHovered">{{ $title }}</span>
        {{ $badge ?? '' }}
    </a>
@endif
