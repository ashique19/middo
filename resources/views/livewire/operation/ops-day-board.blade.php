<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Ops day checklist</h1>
            <p class="text-sm font-semibold text-gray-500">
                Date-wise lunch, box, and cash handoffs (a–h). Live queues + day events with deep links.
            </p>
        </div>
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Delivery / activity date</label>
            <input type="date" wire:model.live="deliveryDate"
                   class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold" />
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl border {{ $report['totals']['attention'] > 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-100 bg-emerald-50' }} p-4">
            <p class="text-[11px] font-bold uppercase {{ $report['totals']['attention'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Needs attention</p>
            <p class="text-2xl font-black {{ $report['totals']['attention'] > 0 ? 'text-rose-900' : 'text-emerald-900' }} mt-1">{{ $report['totals']['attention'] }}</p>
        </div>
        @foreach(array_slice($report['sections'], 0, 3) as $s)
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase text-gray-400">{{ strtoupper($s['id']) }} · {{ $s['title'] }}</p>
                <p class="text-2xl font-black text-middo-dark mt-1">{{ $s['attention'] > 0 ? $s['attention'] : $s['count'] }}</p>
                <p class="text-[10px] text-gray-500 mt-0.5">{{ $s['attention'] > 0 ? 'attention' : 'rows' }}</p>
            </div>
        @endforeach
    </div>

    <div class="space-y-5">
        @foreach($report['sections'] as $section)
            <section class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden" wire:key="ops-day-{{ $section['id'] }}">
                <div class="px-5 py-3 border-b border-gray-50 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-middo-dark">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-gray-100 text-[11px] font-black mr-1">{{ strtoupper($section['id']) }}</span>
                            {{ $section['title'] }}
                            @if($section['attention'] > 0)
                                <span class="ml-2 inline-flex px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200">{{ $section['attention'] }} attention</span>
                            @endif
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $section['blurb'] }}</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($section['counts'] as $key => $val)
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50 border border-gray-100 px-2 py-0.5 rounded-lg">
                                    {{ str_replace('_', ' ', $key) }}: {{ $val }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @if(!empty($deepLinks[$section['id']]))
                        <a href="{{ $deepLinks[$section['id']] }}" class="text-xs font-bold text-middo-orange hover:underline shrink-0">Open board →</a>
                    @endif
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($section['rows'] as $row)
                        <div class="px-5 py-3 flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-middo-dark">
                                    @if(($row['link'] ?? null) === 'box' && \Illuminate\Support\Facades\Route::has('operation.middo-boxes.show'))
                                        <a href="{{ route('operation.middo-boxes.show', $row['id']) }}"
                                           class="font-mono text-middo-orange hover:underline">{{ $row['label'] }}</a>
                                    @elseif(($row['link'] ?? null) === 'handover')
                                        {{ $row['label'] }}
                                    @elseif(($row['link'] ?? null) !== 'box')
                                        <a href="{{ \App\Support\StaffOrderRoutes::show($row['id'], 'middo') }}"
                                           class="font-mono font-bold text-middo-orange hover:underline">#{{ $row['id'] }}</a>
                                        <span class="font-semibold text-gray-800"> · {{ $row['label'] }}</span>
                                    @else
                                        <span class="font-mono">{{ $row['label'] }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $row['meta'] }}</p>
                            </div>
                            <span @class([
                                'shrink-0 inline-flex px-2 py-0.5 rounded-lg text-[11px] font-bold border',
                                'bg-rose-50 text-rose-800 border-rose-200' => ($row['tone'] ?? '') === 'rose',
                                'bg-amber-50 text-amber-800 border-amber-200' => ($row['tone'] ?? '') === 'amber',
                                'bg-sky-50 text-sky-800 border-sky-200' => ($row['tone'] ?? '') === 'sky',
                                'bg-emerald-50 text-emerald-800 border-emerald-200' => ($row['tone'] ?? '') === 'emerald',
                                'bg-violet-50 text-violet-800 border-violet-200' => ($row['tone'] ?? '') === 'violet',
                                'bg-gray-50 text-gray-600 border-gray-200' => !in_array($row['tone'] ?? '', ['rose','amber','sky','emerald','violet'], true),
                            ])>{{ $row['badge'] }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-gray-400 italic">Nothing for this date.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
