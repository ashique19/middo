<div wire:key="corporate-dashboard-root" class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">

    {{-- MAIN DASHBOARD FRAMEWORK CONTAINER --}}
    <div class="max-w-[1400px] mx-auto space-y-6">

        <div class="space-y-6">

            <x-corporate.pending-package-otp-banner :intent="$pendingIntent ?? null" />

            {{-- Dashboard Greeting Segment --}}
            <div>
                <h1 class="text-3xl font-black tracking-tight text-[#2B1A11]">Corporate Dashboard - {{ $customerName }}</h1>
                <p class="text-sm font-semibold text-[#635347] mt-0.5">Welcome back! Manage your office lunches seamlessly.</p>
            </div>

            @php
                $hasActivePackages = ($metrics['active_packages'] ?? 0) > 0;
                $showFooterBoxes = ($metrics['boxes_in_custody'] ?? 0) === 0;
            @endphp

            {{-- TOP KPI GRID ROW — paired rows on small screens, 4 across on xl --}}
            <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-4 xl:gap-4">
                <div @class([
                    'grid gap-4 xl:contents',
                    'grid-cols-2' => $hasActivePackages,
                    'grid-cols-1' => ! $hasActivePackages,
                ])>
                    {{-- Active Orders Card --}}
                    <div @class([
                        'bg-[#1E4630] text-white p-4 rounded-2xl shadow-sm flex items-center gap-4 border border-[#143021]',
                        'xl:col-span-2' => ! $hasActivePackages,
                    ])>
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

                    {{-- Active Packages Card --}}
                    @if($hasActivePackages)
                    <a href="{{ route('corporates.packages.index') }}"
                       class="bg-[#EFE9DC] border border-[#DDD3BE] text-[#2B1A11] p-4 rounded-2xl shadow-sm flex items-center gap-4 hover:border-middo-orange/50 transition">
                        <div class="p-1 text-[#635347]">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-[#635347]">Active Packages:</div>
                            <div class="text-2xl font-black leading-none mt-1">{{ $metrics['active_packages'] }}</div>
                        </div>
                    </a>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4 xl:contents">
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
                            <div class="text-lg font-black leading-tight mt-0.5">{{ $metrics['next_meal_time'] }}</div>
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
                        </div>
                    </div>
                </div>
            </div>

            {{-- MIDDO BOXES IN CUSTODY — prominent when user holds boxes --}}
            @if(($metrics['boxes_in_custody'] ?? 0) > 0)
                <button type="button"
                        @click="$dispatch('open-middo-boxes-custody-modal')"
                        class="w-full bg-amber-50 border border-amber-200 rounded-2xl p-4 shadow-sm flex items-center justify-between gap-4 hover:border-middo-orange/50 transition text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-2xl shrink-0">📦</span>
                        <div class="min-w-0">
                            <div class="text-sm font-black text-[#2B1A11]">Middo Boxes with You</div>
                            <p class="text-xs font-semibold text-[#635347] mt-0.5 truncate">
                                {{ $metrics['boxes_in_custody'] }} {{ str('box')->plural($metrics['boxes_in_custody']) }} at your office — tap to view details
                            </p>
                        </div>
                    </div>
                    <span class="shrink-0 bg-amber-100 text-[#8A441B] px-3 py-1 rounded-full text-xs font-black font-mono">
                        {{ $metrics['boxes_in_custody'] }} with you
                    </span>
                </button>
            @endif

            {{-- ACTIVE PACKAGES SEGMENT --}}
            @if(count($activePackages))
            <div class="space-y-4">
                <div class="border-b border-[#EBE3D3] pb-3 flex justify-between items-center gap-3">
                    <div>
                        <h3 class="text-xl font-black tracking-tight text-[#2B1A11]">Active Packages</h3>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">Prepaid monthly plans currently running for your office.</p>
                    </div>
                    <a href="{{ route('corporates.packages.index') }}" class="shrink-0 text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-1.5 rounded-xl transition flex items-center gap-1 shadow-sm group">
                        <span>All packages</span>
                        <span class="transform group-hover:translate-x-0.5 transition-transform text-[10px]">➔</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($activePackages as $pkg)
                        <a href="{{ $pkg['show_url'] }}"
                           wire:key="dash-active-pkg-{{ $pkg['id'] }}"
                           class="block bg-white border border-[#DDD3BE] rounded-2xl p-4 shadow-sm hover:border-middo-orange/40 hover:shadow-md transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-black text-lg leading-tight truncate">{{ $pkg['name'] }}</div>
                                    <div class="text-xs font-semibold text-[#635347] mt-1">
                                        {{ $pkg['month_label'] }}
                                        · qty {{ $pkg['quantity'] }}
                                        · {{ $pkg['billable_days'] }} days
                                    </div>
                                </div>
                                <span @class([
                                    'shrink-0 text-[10px] font-black uppercase px-2 py-1 rounded-full',
                                    'bg-amber-100 text-amber-900' => ($pkg['schedule_status'] ?? '') === 'awaiting_schedule',
                                    'bg-emerald-100 text-emerald-800' => ($pkg['schedule_status'] ?? '') !== 'awaiting_schedule',
                                ])>
                                    {{ $pkg['schedule_label'] }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs font-bold">
                                <span class="text-[#635347]">
                                    @if(($pkg['schedule_status'] ?? '') === 'awaiting_schedule')
                                        Ops will assign delivery dates
                                    @else
                                        {{ $pkg['pending_days'] }} upcoming · {{ $pkg['completed_days'] }} done
                                    @endif
                                </span>
                                <span class="text-middo-orange">৳{{ number_format($pkg['total_amount']) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- UPCOMING LUNCH SCHEDULES TIMELINE SEGMENT --}}
            <div class="space-y-4">
                <div class="border-b border-[#EBE3D3] pb-3 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-black tracking-tight text-[#2B1A11]">Upcoming Lunch Schedules</h3>
                    </div>
                    
                    <a href="{{ route('corporates.orders.scheduled') }}" class="text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-1.5 rounded-xl transition flex items-center gap-1 shadow-sm group">
                        <span>See All</span>
                        <span class="transform group-hover:translate-x-0.5 transition-transform text-[10px]">➔</span>
                    </a>
                </div>

                {{-- Continuous Matrix Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @forelse($upcomingEvents as $order)
                        <x-operation.dashboard.meal-card :order="$order" :is-history="false" />
                    @empty
                        <div class="col-span-full bg-white border border-[#EBE3D3] rounded-2xl p-10 text-center shadow-sm">
                            <p class="text-sm font-bold text-gray-500">No upcoming lunches scheduled yet.</p>
                            <p class="text-xs text-gray-400 mt-1">Ready to order? <a href="{{ route('menu') }}" class="text-middo-orange font-bold hover:underline">Browse the menu</a> and schedule your next office lunch.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- RECENT OFFICE LUNCHES HISTORY SEGMENT — only when there is history --}}
            @if(count($recentLunches))
            <div class="space-y-4">
                <div class="border-b border-[#EBE3D3] pb-3 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-black tracking-tight text-[#2B1A11]">Recent Office Lunches</h3>
                    </div>
                    
                    <a href="{{ route('corporates.orders.history') }}" class="text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-1.5 rounded-xl transition flex items-center gap-1 shadow-sm group">
                        <span>View History</span>
                        <span class="transform group-hover:translate-x-0.5 transition-transform text-[10px]">➔</span>
                    </a>
                </div>

                {{-- Continuous Matrix Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($recentLunches as $lunch)
                        <x-operation.dashboard.meal-card :order="$lunch" :is-history="true" />
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- FOOTER UTILITY ROW — paired rows; lone tile stretches full width --}}
        <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-6 xl:gap-4 pt-2">
            <div class="grid grid-cols-2 gap-4 xl:contents">
                <a href="{{ route('contact') }}" class="bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors group flex flex-col h-full">
                <svg class="w-5 h-5 text-[#635347] transition-colors group-hover:text-[#8A441B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" />
                </svg>
                <h4 class="text-xs font-black text-[#2B1A11] mt-1.5 group-hover:text-[#8A441B]">Quick Support</h4>
                <p class="text-[9px] text-gray-400 font-medium leading-tight mt-0.5">Get assistance with your orders instantly.</p>
            </a>

            <a href="{{ route('faq') }}" class="bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors group flex flex-col h-full">
                <svg class="w-5 h-5 text-[#635347] transition-colors group-hover:text-[#8A441B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                </svg>
                <h4 class="text-xs font-black text-[#2B1A11] mt-1.5 group-hover:text-[#8A441B]">FAQ</h4>
                <p class="text-[9px] text-gray-400 font-medium leading-tight mt-0.5">Learn more about Middo.</p>
            </a>
            </div>

            <div @class([
                'grid gap-4 xl:contents',
                'grid-cols-2' => $hasActivePackages,
                'grid-cols-1' => ! $hasActivePackages,
            ])>
            <a href="{{ route('menu') }}" @class([
                'bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors group flex flex-col justify-between h-full',
                'xl:col-span-2' => ! $hasActivePackages,
            ])>
                <span class="text-xs font-bold text-[#2B1A11] group-hover:text-[#8A441B]">🍱 Place an Order</span>
                <span class="text-gray-400 text-[10px] mt-2">➔</span>
            </a>

            @if($hasActivePackages)
            <a href="{{ route('corporates.packages.index') }}" class="bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors group flex flex-col justify-between h-full">
                <span class="text-xs font-bold text-[#2B1A11] group-hover:text-[#8A441B]">🗓️ Active Packages</span>
                <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full text-[10px] font-black font-mono w-fit mt-2">{{ $metrics['active_packages'] }}</span>
            </a>
            @endif
            </div>

            <div @class([
                'grid gap-4 xl:contents',
                'grid-cols-2' => $showFooterBoxes,
                'grid-cols-1' => ! $showFooterBoxes,
            ])>
            @if($showFooterBoxes)
            <button type="button" @click="$dispatch('open-middo-boxes-custody-modal')" class="bg-white border border-[#EBE3D3] p-3 rounded-2xl shadow-sm hover:border-[#8A441B] transition-colors group flex flex-col justify-between h-full text-left">
                <span class="text-xs font-bold text-[#2B1A11] group-hover:text-[#8A441B]">📦 Middo Boxes with You</span>
                <span class="bg-amber-100 text-[#8A441B] px-2 py-0.5 rounded-full text-[10px] font-black font-mono w-fit mt-2">0 with you</span>
            </button>
            @endif

            <div @class([
                'bg-white border border-[#EBE3D3] rounded-2xl p-2 shadow-sm flex flex-col h-full',
                'xl:col-span-2' => ! $showFooterBoxes,
            ])>
                <div class="text-[10px] font-black uppercase text-gray-400 px-1 tracking-wider">Delivery Zone</div>
                <div class="w-full flex-1 min-h-[5rem] bg-[#E3DEC3] rounded-xl overflow-hidden relative flex items-center justify-center text-xs font-bold text-[#635347] mt-1.5">
                    <div class="absolute inset-0 bg-cover bg-center opacity-60" style="background-image: url('{{ asset('img/public/how-it-works-corporates.jpg') }}');"></div>
                    <a href="{{ route('corporates.orders.scheduled') }}" class="relative z-10 bg-white/90 px-2 py-1 rounded-full border border-gray-200 shadow-sm hover:bg-white transition text-[10px] font-bold">
                        View Scheduled Orders
                    </a>
                </div>
                <p class="text-[9px] text-gray-400 px-1 pt-1.5 leading-tight">Deliveries are made to your registered office address.</p>
            </div>
            </div>
        </div>

    </div>
</div>