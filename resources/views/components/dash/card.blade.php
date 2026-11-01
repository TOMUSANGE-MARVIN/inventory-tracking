@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/70 dark:border-gray-800 shadow-soft p-4 sm:p-5 flex flex-col min-w-0 overflow-hidden']) }}>
    @if ($title || isset($action))
        <header class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            {{ $action ?? '' }}
        </header>
    @endif
    {{ $slot }}
</section>
