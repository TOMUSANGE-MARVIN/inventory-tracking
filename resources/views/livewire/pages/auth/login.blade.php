<div class="p-6 sm:p-8">
    @if (Auth::viaRemember())
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Welcome back') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('You are signed in on this device.') }}</p>
        <button type="button" wire:click="viaRemember"
            class="mt-6 w-full py-2.5 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 shadow-sm shadow-primary-600/30">
            {{ __('Continue') }}
        </button>
    @else
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Sign in') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Use your staff account to continue.') }}</p>

        <x-auth-session-status class="mt-4" :status="session('status')" />

        <form wire:submit="login" class="mt-6 space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
                <div class="relative mt-1.5">
                    <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username"
                        placeholder="you@sparkle.ug"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 pl-9 pr-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none">
                </div>
                <x-input-error :messages="$errors->get('form.email')" class="mt-1.5" />
            </div>

            <div x-data="{ show: false }">
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Password') }}</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">{{ __('Forgot password?') }}</a>
                    @endif
                </div>
                <div class="relative mt-1.5">
                    <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input wire:model="form.password" id="password" name="password" required autocomplete="current-password"
                        x-bind:type="show ? 'text' : 'password'"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 pl-9 pr-10 py-2.5 text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none">
                    <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 grid place-items-center text-gray-400 hover:text-gray-600"
                        :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                        <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('form.password')" class="mt-1.5" />
            </div>

            <label for="remember" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                    class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500">
                {{ __('Keep me signed in') }}
            </label>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 shadow-sm shadow-primary-600/30 disabled:opacity-70">
                <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="login"></i>
                {{ __('Sign in') }}
            </button>
        </form>
    @endif
</div>
