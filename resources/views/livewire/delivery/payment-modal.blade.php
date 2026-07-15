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
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Customer</span>
                        <span class="font-semibold text-middo-dark">{{ $customerName }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Quantity</span>
                        <span class="font-semibold text-middo-dark">{{ $quantity }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Bill total</span>
                        <span class="font-black text-middo-orange">৳{{ number_format($totalAmount) }}</span>
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
                        <p class="text-sm text-gray-600">
                            Confirm cash received. Order becomes <strong>Delivered and Paid</strong> and ৳{{ number_format($totalAmount) }} is added to your balance.
                        </p>
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="$set('paymentMethod', '')" class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700">Back</button>
                            <button
                                type="button"
                                wire:click="confirmCashPayment"
                                wire:loading.attr="disabled"
                                class="px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
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
                        <p class="text-xs text-gray-500">A payment link for ৳{{ number_format($totalAmount) }} will be sent by SMS.</p>
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="$set('paymentMethod', '')" class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700">Back</button>
                            <button
                                type="button"
                                wire:click="sendOnlinePaymentLink"
                                wire:loading.attr="disabled"
                                class="px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
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
