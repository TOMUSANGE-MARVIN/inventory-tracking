{{-- Timeline of records. Expects: $events, $hidden, $actionIcon; $single = one item (no unit counts or codes). --}}
@php($single = $single ?? false)
<ol class="relative space-y-4 border-l border-gray-200 dark:border-gray-700 ml-3">
    @forelse ($events as $e)
        <li class="pl-6 relative">
            <span class="absolute -left-3.5 top-0 w-7 h-7 rounded-full grid place-items-center ring-4 ring-white dark:ring-gray-900 {{ $e['action']->color() }}"><i class="fas fa-{{ $actionIcon($e['action']) }} text-xs"></i></span>
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $e['action']->label() }}</span>
                <time class="text-xs text-gray-500 whitespace-nowrap">{{ $e['when']->format('j M Y, H:i') }}</time>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                @if (isset($e['clients']) && $single)
                    {{ trans_choice(':n client|:n clients', $e['clients'], ['n' => $e['clients']]) }}
                @elseif (isset($e['clients']))
                    {{ trans_choice(':n client|:n clients', $e['clients'], ['n' => $e['clients']]) }} · {{ trans_choice(':n item|:n items', $e['units'], ['n' => $e['units']]) }}@if ($e['mine'] !== $e['clients']) ({{ __(':n clients with this product', ['n' => $e['mine']]) }})@endif
                @elseif ($single)
                    {{ __('This item') }}
                @else
                {{ trans_choice(':n unit|:n units', $e['units'], ['n' => $e['units']]) }}@if ($e['mine'] !== $e['units']) ({{ __(':n of this product', ['n' => $e['mine']]) }})@endif
                @endif
                @if ($e['route'] && $e['action'] === \App\Enums\ScanAction::DISPATCHED)
                    · {{ $e['location'] }} <i class="fas fa-arrow-right text-xs text-gray-400"></i> {{ $e['route'] }}
                @elseif ($e['route'])
                    · {{ __('at :here, from :there', ['here' => $e['location'], 'there' => $e['route']]) }}
                @else
                    · {{ __('at :here', ['here' => $e['location']]) }}
                @endif
            </div>
            <div class="text-xs text-gray-500">
                @if ($e['by']){{ __('by :name', ['name' => $e['by']]) }}@endif
                @if ($e['transfer']) · <span class="font-mono">{{ $e['transfer'] }}</span>@endif
                @if ($e['note']) · {{ $e['note'] }}@endif
            </div>
            @if ($e['codes'] && ! $single)
                <details class="mt-1">
                    <summary class="text-xs text-primary-600 cursor-pointer select-none">{{ __('Show unit codes') }}</summary>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($e['codes'] as $code)
                            <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{{ $code }}</span>
                        @endforeach
                    </div>
                </details>
            @endif
        </li>
    @empty
        <li class="pl-6 text-sm text-gray-500">{{ __('No records in this period.') }}</li>
    @endforelse
</ol>
@if ($hidden > 0)
    <p class="mt-3 text-xs text-gray-500">{{ trans_choice(':n more scan outside the selected dates — widen the dates to see it.|:n more scans outside the selected dates — widen the dates to see them.', $hidden, ['n' => $hidden]) }}</p>
@endif
