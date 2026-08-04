@php
    $items = [
        ['title' => 'Account', 'route' => 'delivery.account', 'hint' => 'Wallet & request payment'],
        ['title' => 'Custom runs', 'route' => 'delivery.custom-runs', 'hint' => 'Point → point jobs'],
        ['title' => 'Alerts', 'route' => 'delivery.alerts', 'hint' => 'Parcel calls in your areas'],
        ['title' => 'Delivered orders', 'route' => 'delivery.orders.delivered', 'hint' => 'Collect payment & receive boxes'],
        ['title' => 'Cash handovers', 'route' => 'delivery.cash-handovers', 'hint' => 'Due to Middo / hand over'],
        ['title' => 'Kitchen dispatches', 'route' => 'delivery.kitchen-dispatches', 'hint' => 'Lunch runs to accept'],
        ['title' => 'Pending box runs', 'route' => 'delivery.middo-boxes.pending-run', 'hint' => 'Return boxes to kitchen'],
    ];
@endphp

<div x-show="moreOpen" x-cloak class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="More rider tools">
    <div class="absolute inset-0 bg-[#2B1A11]/45 backdrop-blur-[2px]" @click="moreOpen = false"></div>

    <div class="absolute inset-x-0 bottom-0 rounded-t-[1.75rem] bg-[#FDFBF7] border border-[#E5DCC8] shadow-2xl max-h-[80dvh] flex flex-col pb-[env(safe-area-inset-bottom,0px)]"
         x-show="moreOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-8 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         @click.stop>
        <div class="px-5 pt-3 pb-2 flex items-center justify-between border-b border-[#EFE7D8]">
            <div>
                <div class="mx-auto mb-2 h-1 w-10 rounded-full bg-[#D9CFBB] sm:mx-0"></div>
                <h2 class="text-lg font-black text-[#2B1A11]">More</h2>
                <p class="text-xs font-semibold text-[#8A735C]">Rider tools</p>
            </div>
            <button type="button" @click="moreOpen = false"
                    class="w-10 h-10 rounded-xl border border-[#E5DCC8] text-[#635347] grid place-items-center"
                    aria-label="Close">
                ✕
            </button>
        </div>

        <div class="overflow-y-auto px-3 py-3 space-y-1">
            @foreach($items as $item)
                @if(Route::has($item['route']))
                    <a href="{{ route($item['route']) }}"
                       @click="moreOpen = false"
                       @class([
                           'flex items-start gap-3 rounded-2xl px-4 py-3.5 min-h-[3.25rem] transition',
                           'bg-[#1E4630] text-white' => request()->routeIs($item['route']),
                           'hover:bg-[#F4EFE4] text-[#2B1A11]' => ! request()->routeIs($item['route']),
                       ])>
                        <div class="min-w-0">
                            <p class="text-sm font-bold">{{ $item['title'] }}</p>
                            <p @class([
                                'text-xs font-medium mt-0.5',
                                'text-emerald-100/90' => request()->routeIs($item['route']),
                                'text-[#8A735C]' => ! request()->routeIs($item['route']),
                            ])>{{ $item['hint'] }}</p>
                        </div>
                    </a>
                @endif
            @endforeach

            <form action="{{ route('logout') }}" method="POST" class="pt-2">
                @csrf
                <button type="submit"
                        class="w-full text-left rounded-2xl px-4 py-3.5 text-sm font-bold text-red-700 hover:bg-red-50">
                    Log out
                </button>
            </form>
        </div>
    </div>
</div>
