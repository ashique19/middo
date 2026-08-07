<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl my-8">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Payment</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ $orderLabel }} · {{ $menuName }}</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 mb-5 space-y-1 text-sm">
                    @if($hasSeparateReceiver)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Account holder</span>
                            <span class="font-semibold text-middo-dark text-right">{{ $accountHolderName }}<br><span class="text-xs font-medium text-gray-500">{{ $accountHolderMobile }}</span></span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Receiver</span>
                            <span class="font-semibold text-middo-dark text-right">{{ $receiverName }}<br><span class="text-xs font-medium text-gray-500">{{ $customerMobile }}</span></span>
                        </div>
                    @else
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Customer</span>
                            <span class="font-semibold text-middo-dark">{{ $customerName }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Quantity</span>
                        <span class="font-semibold text-middo-dark">{{ $quantity }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Bill total</span>
                        <span class="font-semibold text-middo-dark">৳{{ number_format($totalAmount) }}</span>
                    </div>
                    @if($amountPaid > 0)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Already paid</span>
                            <span class="font-semibold text-emerald-700">৳{{ number_format($amountPaid) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3 pt-1 border-t border-gray-200">
                        <span class="text-gray-500 font-bold">Due now</span>
                        <span class="font-black text-middo-orange">৳{{ number_format($amountDue) }}</span>
                    </div>
                </div>

                @if($errorMessage)
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                        {{ $errorMessage }}
                    </div>
                @endif

                @if($successMessage)
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {{ $successMessage }}
                    </div>
                @endif

                @if($paymentMethod === '')
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            wire:click="selectCash"
                            class="px-4 py-3 rounded-xl border border-gray-300 text-sm font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                            Cash
                        </button>
                        <button
                            type="button"
                            wire:click="selectOnline"
                            class="px-4 py-3 rounded-xl border border-gray-300 text-sm font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                            Online
                        </button>
                    </div>
                @elseif($paymentMethod === 'cash')
                    <div class="space-y-4">
                        <div>
                            <label for="cash-collect-amount" class="block text-sm font-semibold text-gray-700 mb-2">Cash collected</label>
                            <input
                                id="cash-collect-amount"
                                type="number"
                                min="1"
                                max="{{ $amountDue }}"
                                wire:model.live="cashCollectAmount"
                                class="w-full border border-gray-300 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange p-3 text-sm font-mono">
                            <p class="text-xs text-gray-500 mt-1">Full due is ৳{{ number_format($amountDue) }}. Enter less only when the customer pays short.</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 space-y-1 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500">Collection</span>
                                <span class="font-semibold text-middo-dark">৳{{ number_format($cashCollectAmount) }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500">Commission (you keep)</span>
                                <span class="font-semibold text-middo-dark">৳{{ number_format($commissionAmount) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 pt-1 border-t border-gray-200">
                                <span class="font-bold text-gray-700">Due to Middo</span>
                                <span class="font-black text-middo-orange">৳{{ number_format($dueToMiddo) }}</span>
                            </div>
                            @if($cashCollectAmount > 0 && $cashCollectAmount < $amountDue)
                                <div class="flex justify-between gap-3 pt-1 border-t border-amber-100">
                                    <span class="font-bold text-amber-800">Residual customer due</span>
                                    <span class="font-black text-amber-800">৳{{ number_format($amountDue - $cashCollectAmount) }}</span>
                                </div>
                            @endif
                        </div>
                        @if($cashCollectAmount > 0 && $cashCollectAmount < $amountDue)
                            <div>
                                <label for="short-reason" class="block text-sm font-semibold text-gray-700 mb-2">Short collect reason</label>
                                <input
                                    id="short-reason"
                                    type="text"
                                    wire:model="shortReason"
                                    maxlength="200"
                                    class="w-full border border-amber-200 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange p-3 text-sm"
                                    placeholder="e.g. Customer short ৳50 — will pay online">
                            </div>
                            <p class="text-sm text-amber-800">
                                Order stays <strong>Delivered</strong> with residual due. Collect again or send an online link. Hand over only Due from cash you hold.
                            </p>
                        @else
                            <p class="text-sm text-gray-600">
                                Confirm cash received. Order becomes <strong>Delivered and Paid</strong>. Hand over only the Due amount later; keep your commission from the bag.
                            </p>
                        @endif
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                            <button type="button" wire:click="$set('paymentMethod', '')" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700">Back</button>
                            <button
                                type="button"
                                wire:click="confirmCashPayment"
                                wire:loading.attr="disabled"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                                <span wire:loading.remove wire:target="confirmCashPayment">Confirm cash</span>
                                <span wire:loading wire:target="confirmCashPayment">Saving...</span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        <div>
                            <label for="receiver-phone" class="block text-sm font-semibold text-gray-700 mb-2">Receiver phone number</label>
                            <input
                                id="receiver-phone"
                                type="text"
                                wire:model="receiverPhone"
                                class="w-full border border-gray-300 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange p-3 text-sm"
                                placeholder="017XXXXXXXX">
                            @error('receiverPhone')
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                        <p class="text-xs text-gray-500">A payment link for ৳{{ number_format($amountDue) }} due will be sent by SMS.</p>
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                            <button type="button" wire:click="$set('paymentMethod', '')" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700">Back</button>
                            <button
                                type="button"
                                wire:click="sendOnlinePaymentLink"
                                wire:loading.attr="disabled"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                                <span wire:loading.remove wire:target="sendOnlinePaymentLink">Send payment link</span>
                                <span wire:loading wire:target="sendOnlinePaymentLink">Sending...</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
