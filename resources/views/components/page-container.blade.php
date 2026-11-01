@props([
    'title' => null,
    'breadcrumbs' => [],
    'actions' => null,
    'filters' => null,
    'showFilters' => false,
])

<div class="space-y-5">
    <!-- Page Header -->
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            @if (!empty($breadcrumbs))
                <nav aria-label="Breadcrumb" class="mb-1">
                    <ol class="flex flex-wrap items-center gap-1.5 text-xs">
                        @foreach ($breadcrumbs as $breadcrumb)
                            <li class="flex items-center gap-1.5">
                                @if (!$loop->first)
                                    <i class="fas fa-chevron-right text-[8px] text-gray-400"></i>
                                @endif
                                @if (isset($breadcrumb['url']) && !$loop->last)
                                    <a href="{{ $breadcrumb['url'] }}" wire:navigate class="text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ $breadcrumb['label'] }}</a>
                                @else
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $breadcrumb['label'] ?? '' }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            @if ($title)
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white truncate">{{ $title }}</h1>
            @endif
        </div>

        @if ($actions)
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if ($showFilters && $filters)
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 p-3 border border-gray-200 dark:border-gray-700">
            {{ $filters }}
        </div>
    @endif

    <div>
        {{ $slot }}
    </div>
</div>
