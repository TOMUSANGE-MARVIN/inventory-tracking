<div>
    <x-page-container title="{{ __('Movement history') }}" :breadcrumbs="[['label' => __('Stock tracking'), 'url' => route('tracking.overview')], ['label' => __('Movement history')]]">
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:gap-3">
                <select wire:model.live="product" class="form-field">
                    <option value="">{{ __('All products') }}</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="warehouse" class="form-field">
                    <option value="">{{ __('All locations') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="action" class="form-field">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a->value }}">{{ $a->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Phones: compact list. --}}
            <ul class="sm:hidden panel divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($scans as $scan)
                    <li wire:key="hm-{{ $scan->id }}" class="p-3">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('tracking.lookup', ['code' => $scan->code]) }}" wire:navigate class="font-mono text-sm font-semibold text-primary-600">{{ $scan->code }}</a>
                            <span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $scan->action->color() }}">{{ $scan->action->label() }}</span>
                        </div>
                        <div class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ $scan->product?->name }}</div>
                        <div class="text-xs text-gray-500">{{ $scan->warehouse?->name }} · {{ $scan->created_at->format('d M H:i') }} · {{ $scan->user?->name }}</div>
                    </li>
                @empty
                    <li class="p-8 text-center text-gray-500">{{ __('No movements recorded.') }}</li>
                @endforelse
            </ul>

            <div class="hidden sm:block panel overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('When') }}</th>
                            <th class="px-4 py-3">{{ __('Item') }}</th>
                            <th class="px-4 py-3">{{ __('Product') }}</th>
                            <th class="px-4 py-3">{{ __('Action') }}</th>
                            <th class="px-4 py-3">{{ __('Location') }}</th>
                            <th class="px-4 py-3">{{ __('By') }}</th>
                            <th class="px-4 py-3">{{ __('Transfer') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                        @forelse ($scans as $scan)
                            <tr wire:key="h-{{ $scan->id }}">
                                <td class="px-4 py-2 whitespace-nowrap">{{ $scan->created_at->format('d M Y H:i') }}</td>
                                <td class="px-4 py-2"><a href="{{ route('tracking.lookup', ['code' => $scan->code]) }}" wire:navigate class="font-mono text-primary-600 hover:underline">{{ $scan->code }}</a></td>
                                <td class="px-4 py-2">{{ $scan->product?->name }}</td>
                                <td class="px-4 py-2"><span class="whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium {{ $scan->action->color() }}">{{ $scan->action->label() }}</span></td>
                                <td class="px-4 py-2">{{ $scan->warehouse?->name }}</td>
                                <td class="px-4 py-2">{{ $scan->user?->name }}</td>
                                <td class="px-4 py-2">
                                    @if ($scan->transfer)
                                        <a href="{{ route('tracking.receive', $scan->transfer_id) }}" wire:navigate class="text-primary-600 hover:underline font-mono">{{ $scan->transfer->reference }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No movements recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $scans->links() }}
        </div>
    </x-page-container>
</div>
