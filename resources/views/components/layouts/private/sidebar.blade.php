<aside x-data="{ mobileMenuOpen: false, isSidebarExpanded: true }" 
       class="transition-all duration-300" 
       :class="isSidebarExpanded ? 'md:w-64' : 'md:w-20'">
    
    <button @click="mobileMenuOpen = true" class="md:hidden p-4 text-middo-dark">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>

    <div class="fixed inset-y-0 left-0 z-50 bg-[#2D2D2D] text-white transition-all duration-300 transform md:translate-x-0"
         :class="[isSidebarExpanded ? 'w-64' : 'w-20', mobileMenuOpen ? 'translate-x-0' : '-translate-x-full']">
        
        <div class="p-6 flex items-center justify-between">
            <img src="{{ asset('img/settings/logo-white.png') }}" alt="Middo" class="h-8" :class="isSidebarExpanded ? 'block' : 'hidden'">
            
            <button @click="isSidebarExpanded = !isSidebarExpanded" class="hidden md:block text-white hover:text-middo-orange">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isSidebarExpanded ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7'"></path>
                </svg>
            </button>
            <button @click="mobileMenuOpen = false" class="md:hidden text-white">✕</button>
        </div>

        <nav class="mt-6 px-4 space-y-2">
            @if(auth()->user()->role->name === 'admin')
                <a href="{{ route('admin.navrole.index') }}" 
                    class="flex items-center gap-4 px-4 py-3 rounded-xl transition {{ request()->routeIs('admin.navrole.index') ? 'bg-middo-orange' : 'bg-gray-800 hover:bg-gray-700' }}">
                    <span class="text-xl shrink-0">⚙️</span>
                    <span :class="isSidebarExpanded ? 'block' : 'hidden'" class="font-bold">System Navs</span>
                </a>

                <div class="border-t border-gray-700 my-2"></div>
            @endif

            @foreach($navs as $nav)
                {{-- Only render if the route exists to prevent crash --}}
                @if($nav->route_name && Route::has($nav->route_name))
                    @if($nav->children->isEmpty())
                        <a href="{{ route($nav->route_name) }}" 
                        class="flex items-center gap-4 px-4 py-3 rounded-xl transition {{ request()->routeIs($nav->route_name) ? 'bg-middo-orange' : 'hover:bg-gray-700' }}">
                            <span class="text-xl shrink-0">{!! $nav->icon !!}</span>
                            <span :class="isSidebarExpanded ? 'block' : 'hidden'">{{ $nav->title }}</span>
                        </a>
                    @else
                        <div x-data="{ open: false }">
                            <button @click="open = !open; isSidebarExpanded = true" 
                                    class="w-full flex items-center gap-4 px-4 py-3 rounded-xl hover:bg-gray-700">
                                <span class="text-xl shrink-0">{!! $nav->icon !!}</span>
                                <span :class="isSidebarExpanded ? 'block' : 'hidden'">{{ $nav->title }}</span>
                                <svg x-show="isSidebarExpanded" class="w-4 h-4 ml-auto transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            
                            <div x-show="open && isSidebarExpanded" class="ml-4 border-l border-gray-600 space-y-1 mt-1">
                                @foreach($nav->children as $child)
                                    @if($child->route_name && Route::has($child->route_name))
                                        <a href="{{ route($child->route_name) }}" 
                                        class="block px-4 py-2 text-sm hover:text-middo-orange transition {{ request()->routeIs($child->route_name) ? 'text-middo-orange' : 'text-gray-300' }}">
                                            {{ $child->title }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            @endforeach
        </nav>

    </div>

    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false" class="fixed inset-0 bg-black/50 z-40 md:hidden"></div>
</aside>