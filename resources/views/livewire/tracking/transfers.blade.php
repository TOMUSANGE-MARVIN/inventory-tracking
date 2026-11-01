<div>
    <x-page-container title="{{ __('Transfers') }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Transfers')]]">
        <x-slot name="actions">
            @can('transfer_create')
                <x-button href="{{ route('tracking.dispatch') }}" variant="primary" icon="fas fa-truck">{{ __('New dispatch') }}</x-button>
            @endcan
        </x-slot>

        <div class="space-y-4">
            @if (session('tracking'))
                <x-tracking.flash :flash="session('tracking')" />
            @endif

            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-3">
                <select wire:model.live="status" class="form-field">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="warehouse" class="form-field">
                    <option value="">{{ __('All locations') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Phones: one card per transfer, action first. --}}
            <ul class="sm:hidden space-y-3">
                @forelse ($transfers as $t)
                    <li wire:key="tm-{{ $t->id }}">
                        <a href="{{ route('tracking.receive', $t) }}" wire:navigate class="panel block p-4 active:scale-[0.99] transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-mono text-xs text-gray-500">{{ $t->reference }}</div>
                                    <div class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $t->fromWarehouse?->name }} → {{ $t->toWarehouse?->name }}</div>
                                </div>
                                <span class="shrink-0 whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $t->status->color() }}">{{ $t->status->label() }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="text-gray-500">{{ $t->total_qty }} {{ __('units') }} · {{ $t->dispatched_at?->format('d M H:i') }}</span>
                                <span class="font-semibold text-primary-600">{{ $t->status === \App\Enums\TransferStatus::IN_TRANSIT ? __('Scan in') : __('View') }} <i class="fas fa-chevron-right text-xs"></i></span>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="panel p-8 text-center text-gray-500">{{ __('No transfers yet.') }}</li>
                @endforelse
            </ul>

            <div class="hidden sm:block panel overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3">{{ __('Route') }}</th>
                            <th class="px-4 py-3">{{ __('Units') }}</th>
                            <th class="px-4 py-3">{{ __('Dispatched') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                        @forelse ($transfers as $t)
                            <tr wire:key="t-{{ $t->id }}">
                                <td class="px-4 py-3 font-mono">{{ $t->reference }}</td>
                                <td class="px-4 py-3">{{ $t->fromWarehouse?->name }} → {{ $t->toWarehouse?->name }}</td>
                                <td class="px-4 py-3">{{ $t->total_qty }}</td>
                                <td class="px-4 py-3">{{ $t->dispatched_at?->format('d M H:i') }}<div class="text-xs text-gray-500">{{ $t->dispatcher?->name }}</div></td>
                                <td class="px-4 py-3"><span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $t->status->color() }}">{{ $t->status->label() }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('tracking.receive', $t) }}" wire:navigate class="text-primary-600 hover:underline">
                                        {{ $t->status === \App\Enums\TransferStatus::IN_TRANSIT ? __('Scan in / receive') : __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No transfers yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $transfers->links() }}
        </div>
    </x-page-container>
</div>
