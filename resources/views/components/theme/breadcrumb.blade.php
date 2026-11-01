@props(['title', 'parent', 'parentName', 'children' => null, 'childrenName' => null])

{{-- Same header style as x-page-container: breadcrumbs above the title, actions on the right. --}}
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <nav aria-label="Breadcrumb" class="mb-1">
            <ol class="flex flex-wrap items-center gap-1.5 text-xs">
                <li><a href="{{ route('dashboard') }}" wire:navigate class="text-gray-500 hover:text-primary-600 dark:text-gray-400">{{ __('Dashboard') }}</a></li>
                <li class="flex items-center gap-1.5">
                    <i class="fas fa-chevron-right text-[8px] text-gray-400"></i>
                    @if ($children)
                        <a href="{{ $parent }}" wire:navigate class="text-gray-500 hover:text-primary-600 dark:text-gray-400">{{ $parentName }}</a>
                    @else
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $parentName }}</span>
                    @endif
                </li>
                @if ($children)
                    <li class="flex items-center gap-1.5">
                        <i class="fas fa-chevron-right text-[8px] text-gray-400"></i>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $childrenName }}</span>
                    </li>
                @endif
            </ol>
        </nav>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white truncate">{{ $title }}</h1>
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
