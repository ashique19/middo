@php
    $status = $order->order_status ?? 'pending';
    $payment = $order->payment_status ?? 'pending';
    $lens = $payload['lens'] ?? 'middo';
    $lensLabels = [
        'middo' => 'Middo',
        'corporate' => 'Corporate',
        'kitchen' => 'Kitchen',
        'rider' => 'Rider',
    ];
@endphp

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->backRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">← Back to orders</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-middo-dark font-mono">Order #{{ $order->id }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $order->delivery_date?->timezone('Asia/Dhaka')->format('l, M d, Y') }}
                    · {{ $order->delivery_time ?: '—' }}
                    · Qty {{ $order->quantity }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-amber-50 text-amber-900 border border-amber-200">
                    {{ str_replace('_', ' ', $status) }}
                </span>
                <span @class([
                    'inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border',
                    'bg-emerald-50 text-emerald-800 border-emerald-200' => $payment === 'paid',
                    'bg-red-50 text-red-700 border-red-200' => $payment !== 'paid',
                ])>
                    {{ $payment }}
                </span>
                @if($order->isPackageOrder())
                    <x-package-badge :title="$order->packageSubscription?->package?->name ?? 'Meal package'" />
                @endif
            </div>
        </div>
    </div>

    @if($this->canSwitchLenses() && count($availableLenses) > 1)
        <div class="flex flex-wrap gap-1 rounded-2xl border border-gray-200 bg-gray-50 p-1">
            @foreach($availableLenses as $tab)
                <button
                    type="button"
                    wire:click="switchLens('{{ $tab }}')"
                    @class([
                        'px-4 py-2 rounded-xl text-sm font-bold transition',
                        'bg-white text-middo-dark shadow-sm border border-gray-200' => $lens === $tab,
                        'text-gray-500 hover:text-middo-dark' => $lens !== $tab,
                    ])>
                    {{ $lensLabels[$tab] ?? ucfirst($tab) }}
                </button>
            @endforeach
        </div>
        <p class="text-xs font-semibold text-gray-400 -mt-3">
            Viewing as <span class="text-middo-dark">{{ $lensLabels[$lens] ?? $lens }}</span> lens
            @if($lens !== 'middo')
                · ops can intervene with audit
            @endif
        </p>
    @endif

    @if($forceMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $forceMessage }}
        </div>
    @endif
    @if($forceError)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {{ $forceError }}
        </div>
    @endif

    @include('livewire.shared.orders.lenses.'.$lens)
</div>
