<!DOCTYPE html>
<html x-data="mainState(false)" :class="{ rtl: isRtl, dark: isDarkMode }" class="scroll-smooth"
    lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-bind:dir="isRtl ? 'rtl' : 'ltr'"
    x-init="$watch('isRtl', value => document.documentElement.dir = value ? 'rtl' : 'ltr')">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="robots" content="nofollow">

    <title>{{ $title ?? '' }} || {{ settings()->company_name ?? config('app.name') }}</title>
    <!-- Styles -->

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/favicon.png') }}">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="/manifest.json" />
    <link rel="apple-touch-icon" href="/images/icon-192x192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content=" {{ settings()->company_name ?? config('app.name') }}">

    @include('includes.main-css')

    @if ($isDesktop)
        @vite('resources/css/desktop.css')
    @endif

    @stack('styles')

    <script>
        window.themeSettings = <?php echo json_encode(
            settings('app_style', [
                // null keeps the blue palette from app.css unless a colour is chosen in settings
                'primary_color' => null,
                'font_family' => "'Inter', sans-serif",
            ]),
        );
        ?>;
    </script>

    <style>
        [x-cloak] {
            display: none;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles

    @if ($isDesktop)
        @vite('resources/js/desktop.js')
    @endif

    @stack('scripts')
    @livewireScriptConfig

</head>

<body class="antialiased bg-canvas dark:bg-gray-950 text-gray-800 dark:text-gray-200 font-body {{ $isDesktop ? 'desktop-app' : '' }}"
    dir="ltr">
    <x-loading-mask />

    @if (\App\Support\AccessRoles::isWorkerOnly(auth()->user()))
        {{-- Workers only scan items: no menus, stock figures or reports. --}}
        <div class="min-h-screen flex flex-col">
            <header class="sticky top-0 z-30 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800">
                <div class="px-4 sm:px-6 h-14 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-8 h-8 rounded-lg grid place-items-center bg-primary-600 text-white shrink-0"><i class="fas fa-barcode text-sm"></i></span>
                        <div class="min-w-0 leading-tight">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ auth()->user()->station ?: __('Station') }} · {{ auth()->user()->warehouses()->value('name') }}</div>
                        </div>
                    </div>
                    <livewire:layout.worker-logout />
                </div>
            </header>
            <main class="flex-1 px-4 py-5 sm:px-6 w-full max-w-5xl mx-auto">
                {{ $slot ?? '' }}
            </main>
        </div>
    @else
    <div class="flex flex-col min-h-screen">
        <!-- Sidebar -->
        <x-sidebar.sidebar />

        <!-- Page Wrapper -->
        <div class="flex flex-col flex-1 transition-all duration-300 ease-in-out"
            :class="{
                'lg:ml-64': isSidebarOpen || isSidebarHovered,
                'lg:ml-16': !isSidebarOpen && !isSidebarHovered,
            }">

            <!-- Navigation Bar-->
            <x-navbar />

            <!-- Desktop Indicators & Notifications -->
            @if ($isDesktop)
                <div class="fixed top-20 right-6 z-40 flex flex-col gap-3 pointer-events-none">
                    <livewire:desktop-mode-indicator />
                    <livewire:desktop-notification />
                    <livewire:sync-status />
                </div>
            @endif

            <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8">
                <div class="w-full space-y-6">
                    @yield('breadcrumb')

                    @yield('content')

                    @isset($slot)
                        {{-- Dashboard-style pages lay out their own cards; everything else sits on one card. --}}
                        @if ($plain ?? false)
                            {{ $slot }}
                        @else
                            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-soft p-4 sm:p-6 border border-gray-200/70 dark:border-gray-800">
                                {{ $slot }}
                            </div>
                        @endif
                    @endisset

                    {{-- <x-settings-bar /> --}}

                </div>
            </main>

            <!-- Footer -->
            <x-footer />

            {{-- Room for the phone tab bar --}}
            <div class="h-20 lg:hidden"></div>
        </div>
    </div>

    <x-mobile-tabbar />
    @endif

    @if ($isDesktop)
        <livewire:native-event-listener />
    @endif
</body>

</html>
