<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Middo boxes pending run</h1>
        <p class="text-sm font-semibold text-gray-500">
            Boxes currently in your custody. Showing {{ $boxes->count() }} of {{ $boxes->total() }}.
        </p>
    </div>

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[720px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">QR Code</th>
                        <th class="p-4">Model</th>
                        <th class="p-4">Run</th>
                        <th class="p-4">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($nodes as $box)
                        <tr wire:key="pending-box-{{ $box['id'] }}" class="hover:bg-gray-50/70 transition">
                            <td class="p-4 font-mono font-bold text-middo-dark">{{ $box['qr_code_id'] }}</td>
                            <td class="p-4 text-gray-700">{{ $box['model'] }}</td>
                            <td class="p-4">
                                <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                    {{ $box['run_label'] }}
                                </span>
                            </td>
                            <td class="p-4 text-gray-600">
                                @if($box['order_id'])
                                    Order #{{ $box['order_id'] }}
                                    @if($box['menu_name']) · {{ $box['menu_name'] }} @endif
                                    @if($box['customer_name']) · {{ $box['customer_name'] }} @endif
                                @elseif($box['kitchen_name'])
                                    Destination: {{ $box['kitchen_name'] }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                                No Middo boxes in your pending runs.
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
