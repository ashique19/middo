@props(['title' => 'Delivery'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1E4630">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Middo Rider">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <title>{{ $title }} | Middo Rider</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/settings/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/settings/logo.png') }}">
    <link rel="manifest" href="{{ asset('manifest-delivery.webmanifest') }}">
</head>
<body class="delivery-shell text-middo-dark font-sans overflow-x-hidden antialiased"
      x-data="{ moreOpen: false }">

    <div class="min-h-dvh flex kitchen-app-bg md:bg-gray-50">
        <div class="hidden md:block">
            <x-layouts.private.sidebar />
        </div>

        <div class="flex-1 flex flex-col min-w-0">
            <div class="md:hidden">
                <x-delivery.layout.header :title="$title" />
            </div>

            <div class="hidden md:block">
                <x-layouts.private.topbar />
            </div>

            <main class="flex-1 flex flex-col kitchen-app-main pb-[calc(4.75rem+env(safe-area-inset-bottom,0px))] md:pb-0">
                {{ $slot }}
            </main>

            <div class="hidden md:block">
                <x-layouts.private.footer />
            </div>
        </div>
    </div>

    <div class="md:hidden">
        <x-delivery.layout.bottom-nav />
        <x-delivery.layout.more-sheet />
    </div>

    @auth
        <livewire:account.profile-modal />
        <livewire:account.profile-edit-modal />
        <livewire:account.change-password-modal />
    @endauth

    @livewireScripts

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register(@json(asset('sw-delivery.js')), { scope: '/delivery/' }).catch(() => {});
            });
        }
    </script>
</body>
</html>
