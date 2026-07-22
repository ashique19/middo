<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8 overflow-x-hidden">
    <div class="max-w-[1100px] mx-auto w-full min-w-0 space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('corporates.dashboard') }}" class="text-xs font-bold text-middo-orange hover:underline">← Dashboard</a>
                <h1 class="text-3xl font-black tracking-tight mt-1">Meal Packages</h1>
                <p class="text-sm font-semibold text-[#635347] mt-0.5">Pick a rate plan, choose menus for every working day of the month, set off-days, and prepay. Operations schedules the exact dates.</p>
            </div>
            <a href="{{ route('corporates.wallet') }}" class="shrink-0 text-xs font-black uppercase tracking-wider text-[#8A441B] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-2 rounded-xl">
                Wallet & top-up
            </a>
        </div>

        @if (session('message'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-semibold px-4 py-3">
                {{ session('message') }}
            </div>
        @endif

        @if(count($subscriptions))
            <section class="space-y-3">
                <h2 class="text-xs font-black uppercase tracking-wider text-[#635347]">My packages</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($subscriptions as $sub)
                        <a href="{{ route('corporates.packages.show', $sub['id']) }}"
                           class="block bg-white border border-[#DDD3BE] rounded-2xl p-4 hover:border-middo-orange/40 transition shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-black text-lg">{{ $sub['name'] }}</div>
                                    <div class="text-xs font-semibold text-[#635347] mt-0.5">
                                        {{ \Carbon\Carbon::parse($sub['start_date'])->format('M d') }} – {{ \Carbon\Carbon::parse($sub['end_date'])->format('M d, Y') }}
                                        · qty {{ $sub['quantity'] }}
                                    </div>
                                </div>
                                <span class="text-[10px] font-black uppercase px-2 py-1 rounded-full {{ $sub['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ ($sub['schedule_status'] ?? '') === 'awaiting_schedule' ? 'awaiting schedule' : $sub['status'] }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs font-bold">
                                <span>{{ $sub['pending_days'] }} pending · {{ $sub['completed_days'] }} done</span>
                                <span class="text-middo-orange">৳{{ number_format($sub['total_amount']) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-4 min-w-0">
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                @foreach(['all' => 'All', 'classic' => 'Classic', 'veg' => 'Veg', 'vegetarian' => 'Vegetarian', 'protein' => 'Protein', 'light' => 'Light'] as $key => $label)
                    <button type="button" wire:click="$set('filter', '{{ $key }}')"
                        @class([
                            'shrink-0 px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider transition whitespace-nowrap',
                            'bg-[#1E4630] text-white' => $filter === $key,
                            'bg-white border border-[#DDD3BE] text-[#2B1A11] hover:bg-[#EFE9DC]' => $filter !== $key,
                        ])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 min-w-0">
                @forelse($packages as $package)
                    <div wire:key="pkg-card-{{ $package['id'] }}" class="bg-white border border-[#DDD3BE] rounded-2xl overflow-hidden shadow-sm flex flex-col min-w-0">
                        @if($package['thumbnail'])
                            <img src="{{ $package['thumbnail'] }}" alt="" class="w-full h-40 object-cover">
                        @else
                            <div class="w-full h-40 bg-[#EFE9DC] flex items-center justify-center text-[#635347] font-bold text-sm">Meal package</div>
                        @endif
                        <div class="p-5 flex flex-col flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-xl font-black tracking-tight">{{ $package['name'] }}</h3>
                                    <p class="text-xs font-semibold text-[#635347] mt-1 capitalize">{{ $package['diet_tag'] }} · monthly rate</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-2xl font-black text-middo-orange leading-none">৳{{ number_format($package['price_per_day']) }}</div>
                                    <div class="text-[10px] font-bold uppercase text-[#635347] mt-1">per day</div>
                                </div>
                            </div>
                            @if($package['summary'])
                                <p class="text-sm text-[#635347] mt-3">{{ $package['summary'] }}</p>
                            @endif
                            <p class="text-[11px] font-semibold text-gray-400 mt-2">
                                Fill every working day — select menus & day counts at checkout
                            </p>
                            @if(count($package['sample_days']))
                                <div class="mt-3 flex gap-1.5 overflow-x-auto pb-1">
                                    @foreach($package['sample_days'] as $day)
                                        <div class="shrink-0 w-10 h-10 rounded-lg bg-[#F7F4EB] border border-[#E5DCB9] overflow-hidden" title="{{ $day['name'] }}">
                                            @if($day['thumbnail'])
                                                <img src="{{ $day['thumbnail'] }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <button type="button" wire:click="openSubscribe({{ $package['id'] }})"
                                    class="mt-4 w-full bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl transition">
                                Build monthly package
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="md:col-span-2 rounded-2xl border border-dashed border-[#DDD3BE] bg-white/60 p-10 text-center text-sm font-semibold text-[#635347]">
                        No published packages available right now. Check back soon, or order from the <a href="{{ route('menu') }}" class="text-middo-orange font-black hover:underline">daily menu</a>.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <livewire:corporate.package-subscribe-modal />
</div>
