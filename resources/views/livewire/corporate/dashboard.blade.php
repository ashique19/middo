<div wire:key="corporate-dashboard-root" class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">

    {{-- MAIN DASHBOARD FRAMEWORK CONTAINER --}}
    <div class="max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        {{-- LEFT COLUMN CONTAINER MATRIX (Spans 9 Columns) --}}
        <div class="lg:col-span-9 space-y-6">
            
            {{-- Dashboard Greeting Segment --}}
            <div>
                <h1 class="text-3xl font-black tracking-tight text-[#2B1A11]">Corporate Dashboard - {{ $customerName }}</h1>
                <p class="text-sm font-semibold text-[#635347] mt-0.5">Welcome back! Manage your office lunches seamlessly.</p>
            </div>

            {{-- 3-CARD TOP KPI GRID ROW --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Active Orders Card --}}
                <div class="bg-[#1E4630] text-white p-4 rounded-2xl shadow-sm flex items-center gap-4 border border-[#143021]">
                    <div class="p-1 text-emerald-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            <circle cx="10" cy="10" r="1.5" stroke-width="1.5"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-200/70">Active Orders:</div>
                        <div class="text-2xl font-black font-sans leading-none mt-1">{{ $metrics['active_orders'] }}</div>
                    </div>
                </div>

                {{-- Next Meal Card --}}
                <div class="bg-[#EFE9DC] border border-[#DDD3BE] text-[#2B1A11] p-4 rounded-2xl shadow-sm flex items-center gap-4">
                    <div class="p-1 text-[#635347]">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14h1.5v3M13.5 14h1.5v2" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-[#635347]">Next Meal:</div>
                        <div class="text-lg font-black leading-tight mt-0.5">12:00 PM</div>
                        <div class="text-[10px] font-bold text-[#A69988]">(11:30 Delivery)</div>
                    </div>
                </div>

                {{-- Monthly Spend Card --}}
                <div class="bg-[#1E4630] text-white p-4 rounded-2xl shadow-sm flex items-center gap-4 border border-[#143021]">
                    <div class="p-1 text-emerald-300 shrink-0 text-[41px] font-bold">
                        ৳
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-200/70">Monthly Spend:</div>
                        <div class="text-2xl font-black font-sans tracking-tight mt-1">{{ number_format($metrics['monthly_spend'], 0) }}</div>
                        <div class="text-[11px] font-semibold text-emerald-300/90 tracking-tight mt-0.5">Saved: {{ number_format($metrics['monthly_spend'] * 0.1, 0) }}</div>
                    </div>
                </div>
            </div>

            {{-- UPCOMING LUNCH SCHEDULES TIMELINE SEGMENT --}}
            <div class="space-y-4">
                <div class="border-b border-[#EBE3D3] pb-3 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-black tracking-tight text-[#2B1A11]">Upcoming Lunch Schedules</h3>
                    </div>
                    
                    <a href="{{ route('menu') }}" class="text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-1.5 rounded-xl transition flex items-center gap-1 shadow-sm group">
                        <span>See All</span>
                        <span class="transform group-hover:translate-x-0.5 transition-transform text-[10px]">➔</span>
                    </a>
                </div>

                {{-- Continuous Matrix Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @forelse($upcomingEvents as $order)
                        <x-operation.dashboard.meal-card :order="$order" :is-history="false" />
                    @empty
                        <div class="col-span-full bg-white border border-[#EBE3D3] rounded-2xl p-10 text-center text-sm font-semibold text-gray-400 italic shadow-sm">
                            No food tracking operations scheduled throughout the upcoming production cycles.
                        </div>
                    @endforelse
                </div>

                {{-- BIG SEE ALL BUTTON AT THE END OF THE SECTION --}}
                @if(count($upcomingEvents) > 0)
                    <div class="pt-2">
                        <a href="{{ route('menu') }}" class="w-full bg-[#EFE9DC] hover:bg-[#E5DCB9] text-[#8A441B] font-black text-xs uppercase tracking-wider py-4 rounded-2xl shadow-sm border border-[#DDD3BE] transition-all flex items-center justify-center gap-2 group">
                            <span>See All Scheduled Lunches</span>
                            <span class="transform group-hover:translate-x-1 transition-transform">➔</span>
                        </a>
                    </div>
                @endif
            </div>

            {{-- RECENT OFFICE LUNCHES HISTORY SEGMENT --}}
            <div class="space-y-4">
                <div class="border-b border-[#EBE3D3] pb-3 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-black tracking-tight text-[#2B1A11]">Recent Office Lunches</h3>
                    </div>
                    
                    <a href="#" class="text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-1.5 rounded-xl transition flex items-center gap-1 shadow-sm group">
                        <span>View History</span>
                        <span class="transform group-hover:translate-x-0.5 transition-transform text-[10px]">➔</span>
                    </a>
                </div>

                {{-- Continuous Matrix Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @forelse($recentLunches as $lunch)
                        <x-operation.dashboard.meal-card :order="$lunch" :is-history="true" />
                    @empty
                        <div class="col-span-full bg-white border border-[#EBE3D3] rounded-2xl p-10 text-center text-sm font-semibold text-gray-400 italic shadow-sm">
                            No previous food delivery history recorded within this billing cycle.
                        </div>
                    @endforelse
                </div>

                {{-- BIG SEE ALL BUTTON AT THE END OF THE HISTORY SECTION --}}
                @if(count($recentLunches) > 0)
                    <div class="pt-2">
                        <a href="#" class="w-full bg-[#EFE9DC] hover:bg-[#E5DCB9] text-[#8A441B] font-black text-xs uppercase tracking-wider py-4 rounded-2xl shadow-sm border border-[#DDD3BE] transition-all flex items-center justify-center gap-2 group">
                            <span>See All Lunch History</span>
                            <span class="transform group-hover:translate-x-1 transition-transform">➔</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT COLUMN SIDEBAR (Spans 3 Columns) --}}
        <div class="lg:col-span-3 space-y-5 pt-6 lg:pt-0">
            
            {{-- FLOATING USER CARD PROFILE SECTION --}}
            <div class="bg-white border border-[#EBE3D3] rounded-2xl p-4 shadow-sm flex flex-col items-center text-center relative pt-4 lg:pt-8 mt-6 lg:mt-0">
                {{-- Responsive Avatar: Static block layout with margins on mobile, absolute overlap on desktop --}}
                <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-middo-orange bg-amber-100 flex items-center justify-center font-black text-xl text-[#8A441B] -mt-12 mb-2 lg:mt-0 lg:mb-0 lg:absolute lg:-top-8 shadow-sm">
                    {{ substr($customerName, 0, 1) }}
                </div>
                
                <h3 class="text-base font-black text-[#2B1A11] mt-2">{{ $customerName }}</h3>
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-0.5">
                    Balance: 
                    <span class="text-[#1E4630] font-mono font-bold">
                        ৳{{ number_format(auth()->user()->balance ?? 0, 2) }}
                    </span>
                </p>
                
                {{-- DUAL LINK INTERFACE ROW --}}
                <div class="grid grid-cols-2 gap-2 w-full mt-4 pt-3 border-t border-dashed border-gray-100">
                    <a href="#" class="text-[11px] font-black text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] py-2 px-2 rounded-xl transition flex items-center justify-center gap-1.5 border border-[#EBE3D3]/40 shadow-sm group">
                        <svg class="w-3.5 h-3.5 text-[#635347] transition-colors group-hover:text-[#2B1A11]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <span>Profile</span>
                    </a>

                    <a href="#" class="text-[11px] font-black text-white bg-middo-orange hover:bg-[#733614] py-2 px-2 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm group">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Add Money</span>
                    </a>
                </div>
            </div>

            {{-- QUICK MANAGEMENT TOOL LINKS --}}
            <div class="grid grid-cols-2 gap-3">
                {{-- Quick Support Card --}}
                <div class="bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors cursor-pointer group">
                    <svg class="w-5 h-5 text-[#635347] transition-colors group-hover:text-[#8A441B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" />
                    </svg>
                    <h4 class="text-xs font-black text-[#2B1A11] mt-1.5 group-hover:text-[#8A441B]">Quick Support</h4>
                    <p class="text-[9px] text-gray-400 font-medium leading-tight mt-0.5">Get assistance with your orders instantly.</p>
                </div>

                {{-- FAQ Card --}}
                <div class="bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors cursor-pointer group">
                    <svg class="w-5 h-5 text-[#635347] transition-colors group-hover:text-[#8A441B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                    </svg>
                    <h4 class="text-xs font-black text-[#2B1A11] mt-1.5 group-hover:text-[#8A441B]">FAQ</h4>
                    <p class="text-[9px] text-gray-400 font-medium leading-tight mt-0.5">Learn more about Middo.</p>
                </div>
            </div>

            {{-- ACTION OVERLAY BUTTON LINKS --}}
            <div class="bg-white border border-[#EBE3D3] rounded-2xl p-3 shadow-sm space-y-2">
                <a href="{{ route('menu') }}" class="flex items-center justify-between p-2 rounded-xl hover:bg-[#F7F4EB] text-xs font-bold text-[#2B1A11]">
                    <span>🍱 Bulk Orders</span> <span class="text-gray-400">➔</span>
                </a>
                <div class="flex items-center justify-between p-2 rounded-xl hover:bg-[#F7F4EB] text-xs font-bold text-[#2B1A11] cursor-pointer">
                    <span>📦 Return Middo Box</span> <span class="bg-amber-100 text-[#8A441B] px-2 py-0.5 rounded-full text-[10px] font-black font-mono">0 Ready</span>
                </div>
            </div>

            {{-- VISUAL MAP LOGISTICS COMPONENT --}}
            <div class="bg-white border border-[#EBE3D3] rounded-2xl p-2 shadow-sm space-y-2">
                <div class="text-[10px] font-black uppercase text-gray-400 px-1 tracking-wider">Live Delivery Logistics Map</div>
                <div class="w-full h-32 bg-[#E3DEC3] rounded-xl overflow-hidden relative opacity-80 flex items-center justify-center text-xs font-bold text-[#635347]">
                    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://maps.googleapis.com/maps/api/staticmap?center=Dhaka,Gulshan&zoom=13&size=300x150&sensor=false&key=');"></div>
                    <span class="relative z-10 bg-white/90 px-3 py-1.5 rounded-full border border-gray-200 shadow-sm">📍 Track Live Couriers</span>
                </div>
            </div>

        </div>


    </div>
</div>