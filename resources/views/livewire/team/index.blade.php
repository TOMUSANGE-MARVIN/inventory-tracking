@php
    $roleLabel = ['admin' => __('Super admin'), 'branch-admin' => __('Branch admin'), 'worker' => __('Worker')];
    $roleChip = [
        'admin' => 'bg-[#6250d6]/12 text-[#4f3fc0] dark:bg-[#9085e9]/20 dark:text-[#b3abf2]',
        'branch-admin' => 'bg-[#2a78d6]/12 text-[#1f62b3] dark:bg-[#3987e5]/20 dark:text-[#8db8f0]',
        'worker' => 'bg-[#1baf7a]/12 text-[#0f8a5f] dark:bg-[#199e70]/20 dark:text-[#5fd3a8]',
    ];
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('Team & access') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Who can sign in, and what they can see') }}</p>
        </div>
        <button type="button" wire:click="create" class="btn-primary py-2"><i class="fas fa-user-plus"></i>{{ __('Add person') }}</button>
    </div>

    <x-tracking.flash :flash="$flash" />

    <div class="grid gap-4 grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => __('Super admins'), 'value' => $stats['admins'], 'icon' => 'user-shield', 'chip' => $roleChip['admin']],
            ['label' => __('Branch admins'), 'value' => $stats['branchAdmins'], 'icon' => 'store', 'chip' => $roleChip['branch-admin']],
            ['label' => __('Workers'), 'value' => $stats['workers'], 'icon' => 'barcode', 'chip' => $roleChip['worker']],
            ['label' => __('Deactivated'), 'value' => $stats['inactive'], 'icon' => 'user-slash', 'chip' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'],
        ] as $card)
            <div class="panel p-4 flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">{{ $card['label'] }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $card['value'] }}</div>
                </div>
                <span class="w-10 h-10 rounded-xl grid place-items-center {{ $card['chip'] }}"><i class="fas fa-{{ $card['icon'] }}"></i></span>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        {{-- People --}}
        <section class="panel xl:col-span-8 overflow-hidden">
            <div class="p-4 flex flex-wrap items-center gap-2 border-b border-gray-100 dark:border-gray-800">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email…') }}" class="form-field flex-1 min-w-48">
                <select wire:model.live="role" class="form-field">
                    <option value="">{{ __('All roles') }}</option>
                    <option value="admin">{{ __('Super admins') }}</option>
                    <option value="branch-admin">{{ __('Branch admins') }}</option>
                    <option value="worker">{{ __('Workers') }}</option>
                </select>
            </div>

            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($users as $u)
                    @php
                        $r = collect(['admin', 'branch-admin', 'worker'])->first(fn ($name) => $u->roles->contains('name', $name));
                        $isActive = (int) $u->getRawOriginal('status') === 1;
                    @endphp
                    <li wire:key="u-{{ $u->id }}" @class(['p-4 flex flex-wrap items-center gap-3', 'opacity-60' => ! $isActive])>
                        <span class="w-10 h-10 shrink-0 rounded-full grid place-items-center font-semibold text-white {{ ['admin' => 'bg-[#6250d6]', 'worker' => 'bg-[#13805a]'][$r] ?? 'bg-primary-600' }}">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-white truncate">{{ $u->name }}</span>
                                @if ($u->id === auth()->id())
                                    <span class="text-[11px] text-gray-500">({{ __('you') }})</span>
                                @endif
                                @if ($r)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $roleChip[$r] }}">{{ $roleLabel[$r] }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500 dark:bg-gray-800">{{ __('No role') }}</span>
                                @endif
                                @unless ($isActive)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ __('Deactivated') }}</span>
                                @endunless
                            </div>
                            <div class="text-xs text-gray-500 truncate">{{ $u->email }}{{ $u->phone ? ' · ' . $u->phone : '' }}</div>
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-40 text-sm text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <i class="fas fa-map-marker-alt text-xs text-gray-400"></i>
                            {{ $r === 'admin' ? __('All locations') : ($u->warehouses->pluck('name')->join(', ') ?: '—') }}@if ($r === 'worker' && $u->station)<span class="text-gray-500">· {{ $u->station }}</span>@endif
                        </div>
                        <div class="flex items-center gap-1.5 ml-auto">
                            <button type="button" wire:click="toggleActive('{{ $u->id }}')" class="btn-secondary px-3 py-1.5 text-xs">
                                {{ $isActive ? __('Deactivate') : __('Activate') }}
                            </button>
                            <button type="button" wire:click="edit('{{ $u->id }}')" class="btn-primary px-3 py-1.5 text-xs"><i class="fas fa-pen"></i>{{ __('Edit') }}</button>
                        </div>
                    </li>
                @empty
                    <li class="p-8 text-center text-gray-500">{{ __('Nobody matches that search.') }}</li>
                @endforelse
            </ul>
        </section>

        {{-- What each role can do --}}
        <section class="panel xl:col-span-4 p-4 sm:p-5 space-y-4 self-start">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('What each role can do') }}</h2>
            @foreach ([
                'admin' => [__('Every location: Head Office and all branches'), __('Dispatch, receive, scan and look up any item'), __('Products, locations, sales, purchasing, reports'), __('Manage the team and settings')],
                'branch-admin' => [__('Only their own branch'), __('Scan in deliveries sent to their branch'), __('Record items put into use, finished or for refill'), __('Send containers back to Head Office'), __('Their branch\'s dashboard, history, alerts and labels')],
                'worker' => [__('Works at a station in one branch'), __('Scans the item each time it is used on a customer'), __('Sees only the scan screen and their own scans – no stock, reports or menus')],
            ] as $key => $can)
                <div>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $roleChip[$key] }}">{{ $roleLabel[$key] }}</span>
                    <ul class="mt-2 space-y-1.5 text-sm text-gray-700 dark:text-gray-300">
                        @foreach ($can as $line)
                            <li class="flex gap-2"><i class="fas fa-check text-[11px] mt-1 text-[#1baf7a]"></i>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
            <p class="text-xs text-gray-500">{{ __('Branch admins cannot see other branches, users, settings, sales or purchasing.') }}</p>
        </section>
    </div>

    {{-- Add / edit panel --}}
    <div x-data x-show="$wire.panel" x-cloak class="fixed inset-0 z-[90]" @keydown.escape.window="$wire.set('panel', false)">
        <div class="absolute inset-0 bg-gray-950/40 backdrop-blur-[2px]" @click="$wire.set('panel', false)"></div>
        <form wire:submit="save" class="absolute inset-y-0 right-0 w-full sm:max-w-md flex flex-col bg-white dark:bg-gray-900 shadow-2xl">
            <header class="flex items-center justify-between px-5 h-16 shrink-0 border-b border-gray-200/70 dark:border-gray-800">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $editingId ? __('Edit person') : __('Add person') }}</h2>
                <button type="button" @click="$wire.set('panel', false)" class="w-9 h-9 grid place-items-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><i class="fas fa-times"></i></button>
            </header>

            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ __('Role') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        @foreach (['admin' => ['user-shield', __('Head Office, every location')], 'branch-admin' => ['store', __('One branch only')], 'worker' => ['barcode', __('Scans usage only')]] as $value => [$icon, $hint])
                            <label @class([
                                'cursor-pointer rounded-xl border-2 p-3 transition',
                                'border-primary-600 bg-primary-50 dark:bg-primary-900/30' => $userRole === $value,
                                'border-gray-200 dark:border-gray-700' => $userRole !== $value,
                            ])>
                                <input type="radio" wire:model.live="userRole" value="{{ $value }}" class="sr-only">
                                <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><i class="fas fa-{{ $icon }} text-primary-600"></i>{{ $roleLabel[$value] }}</span>
                                <span class="block mt-0.5 text-xs text-gray-500">{{ $hint }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('userRole') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if (in_array($userRole, ['branch-admin', 'worker'], true))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ __('Branch') }}</label>
                        <select wire:model="branchId" class="form-field w-full">
                            <option value="">{{ __('Choose branch…') }}</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                        @error('branchId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($userRole === 'worker')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ __('Station') }}</label>
                        <input type="text" wire:model="station" list="station-names" placeholder="{{ __('e.g. Hair chair 2, Nail bar, Braiding') }}" class="form-field w-full">
                        <datalist id="station-names">
                            @foreach ($stations as $st)<option value="{{ $st }}">@endforeach
                        </datalist>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Where they work. Every scan they make records this station.') }}</p>
                        @error('station') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @foreach ([['name', __('Full name'), 'text', 'name'], ['email', __('Email'), 'email', 'email'], ['phone', __('Phone (optional)'), 'tel', 'tel']] as [$field, $label, $type, $auto])
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $label }}</label>
                        <input type="{{ $type }}" wire:model="{{ $field }}" autocomplete="off" class="form-field w-full">
                        @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $editingId ? __('New password (leave blank to keep)') : __('Password') }}</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" wire:model="password" autocomplete="new-password" class="form-field w-full pr-10">
                        <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 grid place-items-center text-gray-400"><i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters.') }}</p>
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                    <span>
                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Can sign in') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('Turn off to block this person without deleting them.') }}</span>
                    </span>
                    <input type="checkbox" wire:model="active" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                </label>
            </div>

            <footer class="shrink-0 px-5 py-3 border-t border-gray-200/70 dark:border-gray-800 flex gap-2">
                <button type="button" @click="$wire.set('panel', false)" class="btn-secondary flex-1">{{ __('Cancel') }}</button>
                <button type="submit" wire:loading.attr="disabled" class="btn-primary flex-1">{{ $editingId ? __('Save changes') : __('Add person') }}</button>
            </footer>
        </form>
    </div>
</div>
