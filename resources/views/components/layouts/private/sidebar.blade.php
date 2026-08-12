<aside x-data="{ mobileMenuOpen: false, isSidebarExpanded: true }"
       class="transition-all duration-300"
       :class="isSidebarExpanded ? 'md:w-64' : 'md:w-20'">

    <button @click="mobileMenuOpen = true" class="md:hidden p-4 text-middo-dark min-h-[48px] min-w-[48px]" aria-label="Open menu">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>

    <div class="fixed inset-y-0 left-0 z-50 bg-[#2D2D2D] text-white transition-all duration-300 transform md:translate-x-0"
         :class="[isSidebarExpanded ? 'w-64' : 'w-20', mobileMenuOpen ? 'translate-x-0' : '-translate-x-full']">

        <div class="p-6 flex items-center justify-between">
            <img src="{{ asset('img/settings/logo-white.png') }}" alt="Middo" class="h-8" :class="isSidebarExpanded ? 'block' : 'hidden'">

            <button @click="isSidebarExpanded = !isSidebarExpanded" class="hidden md:block text-white hover:text-middo-orange p-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isSidebarExpanded ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7'"></path>
                </svg>
            </button>
            <button @click="mobileMenuOpen = false" class="md:hidden text-white text-2xl p-2 min-h-[44px] min-w-[44px]" aria-label="Close menu">✕</button>
        </div>

        <nav class="mt-2 px-3 pb-6 overflow-y-auto max-h-[calc(100vh-7rem)] space-y-4">
            @foreach($navs as $nav)
                @if($nav->children->isNotEmpty())
                    <div class="space-y-1">
                        <p class="px-4 pt-2 pb-1 text-[11px] font-bold uppercase tracking-[0.14em] text-gray-400"
                           :class="isSidebarExpanded ? 'block' : 'hidden'">
                            {{ $nav->title }}
                        </p>
                        <div class="space-y-1">
                            @foreach($nav->children as $child)
                                @if($child->route_name && Route::has($child->route_name))
                                    <a href="{{ route($child->route_name) }}"
                                       @click="mobileMenuOpen = false"
                                       class="flex items-center gap-4 px-4 py-3 rounded-xl min-h-[48px] transition {{ request()->routeIs($child->route_name) ? 'bg-middo-orange text-white' : 'text-gray-200 hover:bg-gray-700' }}">
                                        <span class="text-xl shrink-0">{!! $child->icon ?? '📄' !!}</span>
                                        <span class="text-sm font-semibold truncate" :class="isSidebarExpanded ? 'block' : 'hidden'">{{ $child->title }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @elseif($nav->route_name && Route::has($nav->route_name))
                    {{-- Legacy flat top-level links (should be rare after section sync). --}}
                    <a href="{{ route($nav->route_name) }}"
                       @click="mobileMenuOpen = false"
                       class="flex items-center gap-4 px-4 py-3.5 rounded-xl min-h-[48px] transition {{ request()->routeIs($nav->route_name) ? 'bg-middo-orange' : 'hover:bg-gray-700' }}">
                        <span class="text-xl shrink-0">{!! $nav->icon ?? '📄' !!}</span>
                        <span :class="isSidebarExpanded ? 'block' : 'hidden'">{{ $nav->title }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

    </div>

    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false" class="fixed inset-0 bg-black/50 z-40 md:hidden"></div>
</aside>
