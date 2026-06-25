<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
    
    <title>{{ $title ?? 'Middo' }} | Elevated Office Dining</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/settings/favicon.ico') }}">
</head>
<body class="bg-gray-50 text-middo-dark font-sans overflow-x-hidden">
    <div class="min-h-screen flex">
        <x-layouts.private.sidebar />

        <div class="flex-1 flex flex-col">
            <x-operation.layout.topbar /> 

            <div class="flex-1 flex flex-col transition-all duration-300">
                {{ $slot }}
            </div>
        </div>
    </div>
    
    <x-operation.layout.footer />
    
    @livewireScripts
</body>
</html>