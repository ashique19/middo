<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Accounts</h1>
        <p class="text-sm font-semibold text-gray-500">
            Money ownership portal. MiddoCashLedger is cash SoT (no Middo login user).
            Dual-control: ops propose handover rejects → you confirm; both can accept Due.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($tiles as $tile)
            <a href="{{ route($tile['route']) }}"
               class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm hover:border-middo-orange transition block space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-bold text-middo-dark">{{ $tile['label'] }}</h2>
                    @if($tile['stat'])
                        <span class="text-sm font-mono font-black text-middo-dark">{{ $tile['stat'] }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500">{{ $tile['hint'] }}</p>
                <span class="inline-block text-xs font-bold text-middo-orange">Open →</span>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Middo money buckets</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            @foreach($buckets as $bucket)
                <div>
                    <dt class="font-bold text-middo-dark">{{ $bucket['name'] }}</dt>
                    <dd class="text-gray-500 mt-0.5">{{ $bucket['desc'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
