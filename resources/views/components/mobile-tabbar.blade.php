{{-- Phone/tablet bottom navigation for the everyday scanning tasks. --}}
@php
    $tabs = [
        ['dashboard', ['dashboard', 'tracking.overview'], 'th-large', __('Home')],
        ['tracking.arrivals', ['tracking.arrivals'], 'box-open', __('Receive')],
        ['tracking.transfers', ['tracking.transfers', 'tracking.dispatch', 'tracking.receive'], 'truck', __('Transfers')],
        ['tracking.station', ['tracking.station'], 'qrcode', __('Scan')],
        ['tracking.lookup', ['tracking.lookup'], 'map-marker-alt', __('Find')],
    ];
@endphp

@can('transfer_access')
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white/95 dark:bg-gray-900/95 backdrop-blur border-t border-gray-200 dark:border-gray-800"
        style="padding-bottom: env(safe-area-inset-bottom)" aria-label="{{ __('Quick navigation') }}">
        <ul class="grid grid-cols-5">
            @foreach ($tabs as [$route, $active, $icon, $label])
                @php($on = request()->routeIs(...$active))
                <li>
                    <a href="{{ route($route) }}" wire:navigate @class([
                        'flex flex-col items-center gap-1 py-2 text-[11px] font-medium',
                        'text-primary-600 dark:text-primary-400' => $on,
                        'text-gray-500 dark:text-gray-400' => ! $on,
                    ])>
                        <span @class(['w-10 h-7 grid place-items-center rounded-full', 'bg-primary-50 dark:bg-primary-900/40' => $on])><i class="fas fa-{{ $icon }}"></i></span>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endcan
