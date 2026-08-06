<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->indexRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">← Subscriptions</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Subscription #{{ $subscription->id }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $subscription->package?->name }} ·
                    {{ $subscription->user?->company_name ?: trim(($subscription->user?->first_name.' '.$subscription->user?->last_name)) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-package-badge />
                <span @class([
                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase',
                    'bg-amber-100 text-amber-800 border border-amber-200' => $subscription->isAwaitingSchedule() || $subscription->isPartiallyScheduled(),
                    'bg-emerald-100 text-emerald-800 border border-emerald-200' => $subscription->status === 'active' && $subscription->isScheduled(),
                    'bg-gray-100 text-gray-600 border border-gray-200' => $subscription->status === 'completed',
                    'bg-red-50 text-red-700 border border-red-200' => $subscription->status === 'cancelled',
                ])>
                    @if($subscription->isAwaitingSchedule())
                        awaiting schedule
                    @elseif($subscription->isPartiallyScheduled())
                        partially scheduled
                    @else
                        {{ $subscription->status }}
                    @endif
                </span>
            </div>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Prepaid</p>
            <p class="text-2xl font-black text-middo-dark">৳{{ number_format($subscription->amount_paid) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $subscription->billable_days }} menu-days · qty {{ $subscription->quantity }} · menu-priced</p>
            <p class="text-xs text-gray-600 mt-2 font-semibold">
                {{ $daySummary['scheduled'] }} scheduled
                @if($daySummary['cancelled'] > 0)
                    · {{ $daySummary['cancelled'] }} cancelled
                    @if($daySummary['refunded_amount'] > 0)
                        · ৳{{ number_format($daySummary['refunded_amount']) }} refunded
                    @endif
                @endif
                @if($daySummary['unconfirmed'] > 0)
                    · {{ $daySummary['unconfirmed'] }} unconfirmed
                @endif
            </p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Window</p>
            <p class="text-lg font-bold text-gray-800">
                @if($subscription->target_month)
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $subscription->target_month)->format('F Y') }}
                @else
                    {{ $subscription->start_date?->format('M d, Y') }} – {{ $subscription->end_date?->format('M d, Y') }}
                @endif
            </p>
            <p class="text-xs text-gray-500 mt-1">
                Omitted weekdays:
                @if(empty($subscription->omitted_weekdays))
                    none
                @else
                    {{ collect($subscription->omitted_weekdays)->map(fn ($d) => \App\Support\PackageBilling::WEEKDAY_LABELS[(int) $d] ?? $d)->implode(', ') }}
                @endif
            </p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Receiver</p>
            @if($corporateUrl = $this->corporateShowRoute($subscription))
                <p class="text-lg font-bold">
                    <a href="{{ $corporateUrl }}" class="text-middo-orange hover:underline">{{ $subscription->receiver_name }}</a>
                </p>
            @else
                <p class="text-lg font-bold text-gray-800">{{ $subscription->receiver_name }}</p>
            @endif
            @if($subscription->user)
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    Wallet ৳{{ number_format((int) $subscription->user->balance) }}
                </p>
            @endif
            <p class="text-xs text-gray-500 mt-1">{{ $subscription->receiver_mobile }} · {{ $subscription->area?->name }}</p>
        </div>
    </div>

    @if($subscription->selections->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <h2 class="text-lg font-bold text-gray-800 mb-3">Corporate menu selection</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($selectionRemaining as $sel)
                    <div class="px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm">
                        <span class="font-bold text-gray-800">{{ $sel['name'] }}</span>
                        <span class="text-gray-500 ml-2">৳{{ number_format($sel['unit_price'] ?? 0) }} · {{ $sel['assigned'] }}/{{ $sel['day_count'] }}</span>
                        @if($subscription->canReceiveScheduleAssignments())
                            <span class="text-xs font-semibold text-amber-700 ml-1">({{ $sel['remaining'] }} left)</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($canManage && (count($scheduleAssignments) > 0))
        <div class="bg-white border border-amber-200 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Confirm delivery days</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Pick a menu and save each day individually.
                    {{ $remainingDays }} prepaid day(s) still unconfirmed.
                    Cancelled days appear at the end with Re-activate.
                    Past cutoff dates are hidden and not editable.
                </p>
            </div>

            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                <table class="w-full text-left min-w-[700px]">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <th class="p-3">Date</th>
                            <th class="p-3">Weekday</th>
                            <th class="p-3">Menu from selection</th>
                            <th class="p-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @foreach($scheduleAssignments as $date => $menuId)
                            @php $cancelledOrder = $cancelledOrdersByDate[$date] ?? null; @endphp
                            <tr wire:key="sched-{{ $date }}" @class(['bg-red-50/40' => $cancelledOrder])>
                                <td class="p-3 font-semibold">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</td>
                                <td class="p-3 text-gray-500">{{ \Carbon\Carbon::parse($date)->format('D') }}</td>
                                <td class="p-3">
                                    @if($cancelledOrder)
                                        <span class="text-gray-400 font-medium">Untagged · cancelled</span>
                                    @else
                                        <select
                                            wire:model="scheduleAssignments.{{ $date }}"
                                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                            <option value="">— leave empty —</option>
                                            @foreach($selectionMenus as $menu)
                                                <option value="{{ $menu->id }}">{{ $menu->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                                <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                    @if($cancelledOrder)
                                        <button
                                            type="button"
                                            wire:click="reactivateOrder({{ $cancelledOrder->id }})"
                                            wire:confirm="Re-activate this day? ৳{{ number_format($this->orderRefundAmount($cancelledOrder)) }} will be debited from the corporate wallet."
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                                            Re-activate
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="saveScheduleDate('{{ $date }}')"
                                            class="px-3 py-1.5 rounded-lg bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold">
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="openCancelUnscheduledModal('{{ $date }}')"
                                            class="px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-700 text-xs font-bold">
                                            Cancel and Refund
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($canManage)
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-gray-800">Delivery details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input wire:model="delivery_time" type="text" placeholder="Delivery time" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <input wire:model="receiver_mobile" type="text" placeholder="Receiver mobile" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <input wire:model="receiver_name" type="text" placeholder="Receiver name" class="rounded-xl border border-gray-200 px-3 py-2 text-sm md:col-span-2">
                <textarea wire:model="address" rows="2" placeholder="Address" class="rounded-xl border border-gray-200 px-3 py-2 text-sm md:col-span-2"></textarea>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="saveDelivery" class="px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold">
                    Update delivery details
                </button>
                @if($isAdmin)
                    <button type="button" wire:click="forceComplete" wire:confirm="Mark this subscription completed without further refunds?" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700">
                        Force complete
                    </button>
                @endif
            </div>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-800">Delivery days</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[900px]">
                <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Order</th>
                        <th class="p-4">Date</th>
                        <th class="p-4">Menu</th>
                        <th class="p-4">Group</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Amount</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($subscription->orders as $order)
                        <tr wire:key="sub-order-{{ $order->id }}">
                            <td class="p-4 font-mono font-semibold">
                                <div class="flex items-center gap-2">
                                    #{{ $order->id }}
                                    <x-package-badge />
                                </div>
                            </td>
                            <td class="p-4">{{ $order->delivery_date->format('D, M d') }} · {{ $order->delivery_time }}</td>
                            <td class="p-4 font-semibold">
                                @if($order->order_status === 'cancelled')
                                    <span class="text-gray-400 font-medium">Untagged</span>
                                @else
                                    {{ $order->menuItem?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="p-4 text-xs text-middo-orange font-semibold">{{ $order->orderGroup?->name ?? 'Ungrouped' }}</td>
                            <td class="p-4 capitalize">{{ $order->order_status }}</td>
                            <td class="p-4 text-right">৳{{ number_format($order->total_amount) }}</td>
                            <td class="p-4 text-right space-x-2 whitespace-nowrap">
                                @if($canManage && $order->order_status === 'pending')
                                    <button type="button" wire:click="openSwapModal({{ $order->id }})" class="text-xs font-bold text-sky-700 hover:underline">Swap</button>
                                    <button type="button" wire:click="unconfirmOrder({{ $order->id }})" wire:confirm="Undo confirmation for this day? It will return to the unconfirmed list (no refund)." class="text-xs font-bold text-amber-700 hover:underline">Undo</button>
                                    <button type="button" wire:click="openCancelModal({{ $order->id }})" class="text-xs font-bold text-red-600 hover:underline">Cancel and Refund</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-sm text-gray-500">
                                @if($subscription->isAwaitingSchedule())
                                    No delivery days yet — assign the schedule above.
                                @else
                                    No delivery days on this subscription.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-800">Package audit log</h2>
            <p class="text-xs text-gray-500 mt-1">Latest first</p>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($auditEvents as $event)
                <div wire:key="pkg-event-{{ $event->id }}" class="px-5 py-3 flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-800">{{ $event->summary }}</p>
                        @if(!empty($event->meta['reason']))
                            <p class="text-sm text-gray-700 mt-1">
                                <span class="font-semibold text-gray-500">Reason:</span> {{ $event->meta['reason'] }}
                            </p>
                        @endif
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ str_replace('_', ' ', $event->type) }}
                            @if($event->createdBy)
                                · {{ $event->createdBy->first_name }} {{ $event->createdBy->last_name }}
                            @endif
                        </p>
                    </div>
                    <p class="text-xs text-gray-400 font-medium whitespace-nowrap">{{ $event->created_at?->timezone(\App\Support\OrderCutoff::timezone())->format('M d, Y g:i A') }}</p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-gray-500">No audit events yet.</div>
            @endforelse
        </div>
    </div>

    @if($showSwapModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl my-8 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Swap menu</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            @if($swapOrder)
                                Order #{{ $swapOrder->id }} · {{ $swapOrder->delivery_date->format('D, M d') }}
                            @else
                                Choose a menu from this package selection.
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeSwapModal" class="text-gray-400 hover:text-gray-600 text-sm font-bold">Close</button>
                </div>
                <select wire:model="swapMenuItemId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Select menu…</option>
                    @foreach($selectionMenus as $menu)
                        <option value="{{ $menu->id }}">{{ $menu->name }} (৳{{ number_format($menu->price) }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500">Only pending days before cutoff can change menu.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeSwapModal" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700">Cancel</button>
                    <button type="button" wire:click="confirmSwap" class="px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold">Confirm swap</button>
                </div>
            </div>
        </div>
    @endif

    @if($showCancelModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl my-8 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Cancel and Refund</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            @if($cancelOrder)
                                Order #{{ $cancelOrder->id }} · {{ $cancelOrder->delivery_date->format('D, M d') }}
                                · {{ $cancelOrder->menuItem?->name }}
                                · refund ৳{{ number_format($this->orderRefundAmount($cancelOrder)) }}
                            @elseif($cancelDate)
                                Unconfirmed {{ \Carbon\Carbon::parse($cancelDate)->format('D, M d, Y') }}
                                @if($cancelMenuItemId)
                                    · estimated refund ৳{{ number_format($this->estimatedUnscheduledRefund()) }}
                                @endif
                            @else
                                Cancel this delivery day and refund the corporate wallet.
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeCancelModal" class="text-gray-400 hover:text-gray-600 text-sm font-bold">Close</button>
                </div>
                @if($cancelDate && ! $cancelOrderId)
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Prepaid menu to cancel</label>
                        <select wire:model.live="cancelMenuItemId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">Select menu…</option>
                            @foreach($selectionRemaining as $sel)
                                @if($sel['remaining'] > 0)
                                    <option value="{{ $sel['menu_item_id'] }}">
                                        {{ $sel['name'] }} ({{ $sel['remaining'] }} left · ৳{{ number_format($sel['unit_price']) }}/day)
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Reason</label>
                    <textarea
                        wire:model="cancelReason"
                        rows="3"
                        maxlength="500"
                        placeholder="Why is this day being cancelled?"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Required. Saved to the package audit log.</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCancelModal" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700">Back</button>
                    <button type="button" wire:click="confirmCancelAndRefund" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-bold">Confirm cancel &amp; refund</button>
                </div>
            </div>
        </div>
    @endif
</div>
