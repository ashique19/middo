<div wire:key="corporate-scheduled-orders" class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">
    <div class="max-w-[1400px] mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('corporates.dashboard') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-bold text-[#8A441B] hover:text-[#733614] mb-2 transition-colors">
                    <span>←</span>
                    <span>Back to Dashboard</span>
                </a>
                <h1 class="text-3xl font-black tracking-tight text-[#2B1A11]">Scheduled Lunches</h1>
                <p class="text-sm font-semibold text-[#635347] mt-0.5">
                    {{ count($orders) }} upcoming {{ str('order')->plural(count($orders)) }} on your calendar.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-orders.view-mode-toggle :view-mode="$viewMode" />
                <a href="{{ route('menu') }}"
                   class="inline-flex items-center gap-1.5 bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl shadow-sm transition-colors">
                    <span>Place New Order</span>
                    <span>➔</span>
                </a>
            </div>
        </div>

        @if($viewMode === 'list')
            <x-operation.orders.table :orders="$orders" :show-view-action="false" :complaint-clickable="true" empty-message="No upcoming lunch schedules found." />
        @else
            {{-- Orders Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($orders as $order)
                    <x-operation.dashboard.meal-card :order="$order" :is-history="false" />
                @empty
                    <div class="col-span-full bg-white border border-[#EBE3D3] rounded-2xl p-12 text-center shadow-sm">
                        <p class="text-sm font-semibold text-gray-400 italic">No upcoming lunch schedules found.</p>
                        <a href="{{ route('menu') }}"
                           class="inline-block mt-4 text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-4 py-2 rounded-xl transition">
                            Browse Menu &amp; Schedule
                        </a>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</div>
