<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->backRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">
            ← Back
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $staffRole }} profile</p>
                <h1 class="text-3xl font-bold text-middo-dark">
                    {{ $staff->name ?: trim($staff->first_name.' '.$staff->last_name) }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $staff->mobile }}
                    @if($staff->email)
                        · {{ $staff->email }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span @class([
                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border',
                    'bg-emerald-100 text-emerald-800 border-emerald-200' => $staff->status === 'active',
                    'bg-yellow-100 text-yellow-800 border-yellow-200' => $staff->status === 'pending',
                    'bg-gray-100 text-gray-600 border-gray-200' => ! in_array($staff->status, ['active', 'pending'], true),
                ])>
                    {{ $staff->status === 'inactive' ? 'suspended' : $staff->status }}
                </span>
                @if($this->canManageKitchenStatus())
                    @if($staff->status !== 'active')
                        <button type="button"
                                wire:click="activate"
                                wire:confirm="Activate {{ $staff->name }}? They will be able to log in."
                                class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition">
                            Activate
                        </button>
                    @endif
                    @if($staff->status !== 'inactive')
                        <button type="button"
                                wire:click="suspend"
                                wire:confirm="Suspend {{ $staff->name }}? They will not be able to log in."
                                class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold border border-red-200 text-red-600 hover:bg-red-50 transition">
                            Suspend
                        </button>
                    @endif
                @endif
                @if($this->kitchenOrdersRoute())
                    <a href="{{ $this->kitchenOrdersRoute() }}"
                       class="inline-flex px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                        All kitchen orders →
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-3 rounded-xl text-sm font-semibold">
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Total orders</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['total_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Active</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['active_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Delivered</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['delivered_orders']) }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
        <h2 class="text-lg font-bold text-middo-dark mb-4">Profile details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Name</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $staff->first_name }} {{ $staff->last_name }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Mobile</dt>
                <dd class="font-mono font-semibold text-gray-800 mt-0.5">{{ $staff->mobile ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Email</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $staff->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Address</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $staff->address ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Location</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $staff->area_name ?: '—' }}@if($staff->city_name), {{ $staff->city_name }}@endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Joined</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $staff->created_at?->timezone('Asia/Dhaka')->format('M d, Y') ?: '—' }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-middo-dark">
                {{ $staffRole === 'kitchen' ? 'Kitchen orders' : 'Delivery orders' }}
            </h2>
            <p class="text-xs font-semibold text-gray-400">Newest first</p>
        </div>
        <x-operation.orders.table
            :orders="$orderRows"
            :show-group="$staffRole === 'kitchen'"
            empty-message="No orders linked to this profile yet." />
        @if($orders->hasPages())
            <div class="px-1">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
