<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Incoming</h1>
        <p class="text-sm font-semibold text-gray-500">
            Boxes on the way to your kitchen. Showing {{ $boxes->count() }} of {{ $boxes->total() }}.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[720px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">QR Code</th>
                        <th class="p-4">Model</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Held by</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($boxes as $box)
                        <tr wire:key="incoming-box-{{ $box->id }}" class="hover:bg-gray-50/70 transition">
                            <td class="p-4 font-mono font-bold text-middo-dark">{{ $box->qr_code_id }}</td>
                            <td class="p-4 text-gray-700">{{ str($box->box_model_type)->headline() }}</td>
                            <td class="p-4">
                                <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                    On the way to kitchen
                                </span>
                            </td>
                            <td class="p-4 text-gray-600">{{ $box->heldByUser?->name ?? '—' }}</td>
                            <td class="p-4 text-right">
                                <button
                                    type="button"
                                    wire:click="acceptBox({{ $box->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="acceptBox({{ $box->id }})"
                                    wire:confirm="Accept this box into your kitchen inventory?"
                                    class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    <span wire:loading.remove wire:target="acceptBox({{ $box->id }})">Accept</span>
                                    <span wire:loading wire:target="acceptBox({{ $box->id }})">Accepting...</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                                No boxes currently on the way to your kitchen.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($boxes->hasPages())
        <div class="mt-4 px-1">
            {{ $boxes->links() }}
        </div>
    @endif
</div>
