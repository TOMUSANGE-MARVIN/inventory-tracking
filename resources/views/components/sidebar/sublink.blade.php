@props(['title' => '', 'active' => false, 'icon' => null])

<a {{ $attributes->merge(['class' => 'flex items-center gap-2 py-1.5 px-3 rounded-md text-sm transition-colors duration-150 '
    . ($active
        ? 'text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 font-semibold'
        : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800')]) }}
    wire:navigate>
    <span class="whitespace-nowrap overflow-hidden text-ellipsis">{{ $title }}</span>
</a>
