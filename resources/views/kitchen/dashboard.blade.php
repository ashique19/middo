{{-- Legacy view kept for compatibility; Livewire route is kitchen.dashboard. --}}
<x-kitchen.layout.app>
    <div class="max-w-7xl mx-auto py-10 px-6">
        <p class="text-sm text-gray-500">
            Loading kitchen dashboard…
            <a href="{{ route('kitchen.dashboard') }}" class="text-middo-orange font-semibold underline">Continue</a>
        </p>
        <script>
            window.location.replace(@json(route('kitchen.dashboard')));
        </script>
    </div>
</x-kitchen.layout.app>
