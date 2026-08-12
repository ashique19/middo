<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-3xl shadow-2xl">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Box Logs</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            QR Code: <span class="font-mono font-semibold text-middo-dark">{{ $boxQrCode }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">
                        ✕
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-left border-collapse min-w-[640px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="p-3">Date</th>
                                <th class="p-3">Custody Status</th>
                                <th class="p-3">Log Action</th>
                                <th class="p-3">Notes</th>
                                <th class="p-3">Order</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($logs as $log)
                                <tr wire:key="middo-box-log-{{ $log['id'] }}" class="hover:bg-gray-50/70 transition">
                                    <td class="p-3 text-gray-600 whitespace-nowrap">{{ $log['created_at'] }}</td>
                                    <td class="p-3">
                                        <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide border bg-sky-50 text-sky-700 border-sky-200/70">
                                            {{ str($log['custody_status'])->headline() }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-medium text-gray-800">
                                        {{ str($log['log_action'])->headline() }}
                                    </td>
                                    <td class="p-3 text-gray-600 max-w-md whitespace-normal break-words">
                                        {{ $log['notes'] ?: '—' }}
                                    </td>
                                    <td class="p-3 font-mono text-gray-600">
                                        {{ $log['order_id'] ? '#'.$log['order_id'] : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-10 text-center text-sm font-semibold text-gray-400 italic">
                                        No logs recorded for this box yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-5">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
