<!DOCTYPE html>
<html x-data="mainState" :class="{ dark: isDarkMode }" class="scroll-smooth" lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
    <meta name="csrf_token" value="{{ csrf_token() }}"/>
    <meta name="robots" content="nofollow">

    <title>{{ $title ?? '' }} · {{ settings()->company_name ?? config('app.name') }}</title>

    <link rel="icon" href="{{ asset('images/favicon.png') }}">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="/manifest.json" />
    <link rel="apple-touch-icon" href="/images/icon-192x192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ settings()->company_name ?? config('app.name') }}">

    @vite('resources/css/app.css')

    @livewireStyles

    @stack('styles')
    @php($appStyle = settings('app_style', []))
    {{-- Only override the blue theme when a colour has been chosen in settings. --}}
    @if (! empty($appStyle['primary_color']))
        <style>
            :root {
                @foreach (generate_color_palette($appStyle['primary_color']) as $weight => $hex)
                    --color-primary-{{ $weight }}: {{ $hex }};
                @endforeach
            }
        </style>
    @endif
    <style>[x-cloak] { display: none; }</style>

    @vite('resources/js/app.js')

    @livewireScriptConfig
</head>

<body class="font-sans antialiased bg-canvas dark:bg-gray-950 text-gray-900 dark:text-gray-100">
    <div class="min-h-screen grid lg:grid-cols-2">
        {{-- Brand panel (desktop) --}}
        <aside class="hidden lg:flex relative overflow-hidden flex-col justify-between p-10 xl:p-14 bg-gradient-to-br from-primary-700 via-primary-600 to-primary-500 text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -left-16 w-[28rem] h-[28rem] rounded-full bg-primary-900/30 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-white/15 ring-1 ring-white/25 grid place-items-center"><i class="fas fa-route"></i></span>
                <div class="leading-none">
                    <div class="text-lg font-extrabold">{{ settings()->company_name ?? config('app.name') }}</div>
                    <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-primary-100">{{ __('Stock tracking') }}</div>
                </div>
            </div>

            <div class="relative max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight">{{ __('Know where every bottle is.') }}</h2>
                <p class="mt-4 text-primary-100 text-lg">{{ __('From delivery at Head Office to the branch chair – every unit scanned, every move recorded.') }}</p>

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['qrcode', __('Scan barcodes and QR labels with any phone')],
                        ['truck', __('Follow stock from Head Office to all branches')],
                        ['exclamation-triangle', __('Missing items flagged the moment they happen')],
                    ] as [$icon, $text])
                        <li class="flex items-center gap-3">
                            <span class="w-9 h-9 shrink-0 rounded-lg bg-white/15 ring-1 ring-white/20 grid place-items-center"><i class="fas fa-{{ $icon }} text-sm"></i></span>
                            <span class="text-primary-50">{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-primary-200">&copy; {{ date('Y') }} {{ settings()->company_name ?? config('app.name') }}</p>
        </aside>

        {{-- Form side --}}
        <main class="flex flex-col items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-sm">
                {{-- Brand (mobile) --}}
                <div class="lg:hidden mb-8 flex items-center justify-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-primary-600 text-white grid place-items-center shadow-lg shadow-primary-600/30"><i class="fas fa-route"></i></span>
                    <div class="leading-none">
                        <div class="text-lg font-extrabold">{{ settings()->company_name ?? config('app.name') }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">{{ __('Stock tracking') }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/70 dark:border-gray-800 shadow-soft">
                    {{ $slot }}
                </div>

                <div class="mt-6 flex justify-center">
                    <button type="button" @click="toggleTheme()" class="inline-flex items-center gap-2 text-xs text-gray-500 hover:text-primary-600">
                        <i x-show="!isDarkMode" class="fas fa-moon"></i><i x-show="isDarkMode" class="fas fa-sun"></i>
                        <span x-text="isDarkMode ? '{{ __('Light mode') }}' : '{{ __('Dark mode') }}'"></span>
                    </button>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
