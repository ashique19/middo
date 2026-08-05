<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Ground marketing</h1>
        <p class="text-sm text-gray-500">Create company leads, book HR visits, and OTP-verify employee signups on site.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach([
            'companies' => 'My companies',
            'leads' => 'Leads',
            'upcoming' => 'Upcoming visits',
            'active' => 'Active (signed up)',
        ] as $key => $label)
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p>
                <p class="font-mono text-2xl font-black text-middo-dark mt-1">{{ number_format($stats[$key]) }}</p>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('marketing.companies.index') }}"
           class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Companies →</a>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b text-sm font-bold text-middo-dark">Upcoming appointments</div>
        <ul class="divide-y">
            @forelse($upcoming as $appt)
                <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div>
                        <a href="{{ route('marketing.companies.show', $appt->company_id) }}" class="font-bold text-middo-orange hover:underline">
                            {{ $appt->company?->name }}
                        </a>
                        <p class="text-xs text-gray-500">{{ $appt->hr_name ?: 'HR' }} · {{ $appt->hr_mobile ?: '—' }}</p>
                    </div>
                    <span class="font-mono text-xs text-gray-600">
                        {{ $appt->scheduled_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}
                    </span>
                </li>
            @empty
                <li class="p-8 text-center text-gray-400 italic text-sm">No upcoming appointments. Create a company lead and book a visit.</li>
            @endforelse
        </ul>
    </div>
</div>
