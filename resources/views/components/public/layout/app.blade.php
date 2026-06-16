<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Middo | Elevated Office Dining</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/settings/favicon.ico') }}">
</head>
<body class="bg-middo-cream text-middo-dark font-sans">

    <x-public.layout.header />

    <main>
        {{ $slot }}
    </main>

    <x-public.layout.footer />

</body>
</html>