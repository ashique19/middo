<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    @foreach($menu as $dish)
        {{-- Keeps your exact layout block styling, but now captures the reactive data parameters --}}
        <x-public.menu.meal-card :dish="$dish" />
    @endforeach
</div>