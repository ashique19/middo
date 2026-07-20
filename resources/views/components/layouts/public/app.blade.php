<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Middo | Elevated Office Dining</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/settings/favicon.ico') }}">
</head>
<body class="bg-middo-cream text-middo-dark font-sans overflow-x-hidden">

    <x-layouts.public.header />

    <main>
        {{ $slot }}
    </main>

    @auth
        <livewire:public.order-checkout-modal />
        <livewire:corporate.delete-order-modal />
        <livewire:corporate.track-order-modal />
        <livewire:corporate.complaint-support-modal />
        <livewire:account.profile-modal />
        <livewire:account.profile-edit-modal />
        <livewire:account.change-password-modal />
        <livewire:account.wallet-top-up-modal />
        <livewire:corporate.middo-boxes-custody-modal />
    @endauth

    <x-layouts.public.footer />

</body>
</html>