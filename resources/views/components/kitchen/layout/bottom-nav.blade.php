@php
    $tabs = [
        [
            'label' => 'Home',
            'route' => 'kitchen.dashboard',
            'match' => 'kitchen.dashboard',
            'icon' => 'home',
        ],
        [
            'label' => 'Orders',
            'route' => 'kitchen.orders.active',
            'match' => 'kitchen.orders.*',
            'icon' => 'orders',
        ],
        [
            'label' => 'Groups',
            'route' => 'kitchen.order-groups.middo',
            'match' => 'kitchen.order-groups.*',
            'icon' => 'groups',
        ],
        [
            'label' => 'Prep',
            'route' => 'kitchen.menus.today',
            'match' => ['kitchen.menus.*', 'kitchen.prep.*'],
            'icon' => 'prep',
        ],
    ];

    $isActive = function (array $tab): bool {
        $match = $tab['match'];
        if (is_array($match)) {
            foreach ($match as $pattern) {
                if (request()->routeIs($pattern)) {
                    return true;
                }
            }

            return false;
        }

        return request()->routeIs($match);
    };
@endphp

<nav class="kitchen-bottom-nav fixed inset-x-0 bottom-0 z-40 md:hidden pb-[env(safe-area-inset-bottom,0px)]"
     aria-label="Kitchen primary">
    <div class="mx-auto max-w-lg px-2 pt-1.5 pb-1.5">
        <div class="grid grid-cols-5 gap-0.5 rounded-[1.35rem] border border-[#E5DCC8] bg-[#FDFBF7]/95 shadow-[0_10px_30px_rgba(43,26,17,0.12)] backdrop-blur-md px-1 py-1">
            @foreach($tabs as $tab)
                @php $active = $isActive($tab); @endphp
                <a href="{{ route($tab['route']) }}"
                   @class([
                       'flex flex-col items-center justify-center gap-0.5 rounded-2xl py-2 min-h-[3.25rem] transition',
                       'bg-[#1E4630] text-white shadow-sm' => $active,
                       'text-[#635347] hover:text-[#2B1A11]' => ! $active,
                   ])>
                    <span class="w-5 h-5" aria-hidden="true">
                        @include('components.kitchen.layout.icons', ['name' => $tab['icon'], 'active' => $active])
                    </span>
                    <span class="text-[10px] font-bold tracking-wide">{{ $tab['label'] }}</span>
                </a>
            @endforeach

            <button type="button"
                    @click="moreOpen = true"
                    @class([
                        'flex flex-col items-center justify-center gap-0.5 rounded-2xl py-2 min-h-[3.25rem] transition',
                        'bg-[#1E4630] text-white shadow-sm' => false,
                        'text-[#635347] hover:text-[#2B1A11]',
                    ])>
                <span class="w-5 h-5" aria-hidden="true">
                    @include('components.kitchen.layout.icons', ['name' => 'more', 'active' => false])
                </span>
                <span class="text-[10px] font-bold tracking-wide">More</span>
            </button>
        </div>
    </div>
</nav>
