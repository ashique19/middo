<div>
    @if($showModal && !empty($order))
        <div wire:key="complaint-support-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-[#EBE3D3] overflow-hidden max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3] shrink-0">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">Complaint / Support</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">
                            Order #{{ $orderId }}
                            · {{ $order['menu_item']['name'] ?? 'Custom Selection' }}
                            @if($hasExistingComplaint)
                                ·
                                <span @class([
                                    'font-bold',
                                    'text-amber-700' => ! $complaintResolved,
                                    'text-emerald-700' => $complaintResolved,
                                ])>
                                    {{ $complaintResolved ? 'Complete' : 'Open' }}
                                </span>
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 overflow-y-auto flex-1 space-y-4">
                    <div class="bg-[#F9F6F0] border border-[#EBE3D3] rounded-xl p-4 text-xs font-semibold text-[#635347] space-y-1">
                        <p><span class="font-black text-[#2B1A11]">Date:</span> {{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }} · {{ $order['delivery_time'] }}</p>
                        <p><span class="font-black text-[#2B1A11]">Status:</span> <span class="capitalize">{{ str_replace('_', ' ', $order['order_status'] ?? 'pending') }}</span></p>
                        <p><span class="font-black text-[#2B1A11]">Total:</span> ৳{{ number_format($order['total_amount'], 0) }}</p>
                    </div>

                    @if($successMessage)
                        <div class="bg-[#1E4630] text-white text-sm font-bold px-4 py-3 rounded-xl shadow-sm leading-relaxed">
                            {{ $successMessage }}
                        </div>
                    @endif
                    @if($errorMessage)
                        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm font-bold px-4 py-3 rounded-xl leading-relaxed">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    @if(count($thread) > 0)
                        <div class="space-y-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Conversation</p>

                            @foreach($thread as $entry)
                                <div class="flex {{ $entry['is_reply'] ? 'justify-start' : 'justify-end' }}">
                                    <div class="max-w-[85%] rounded-2xl px-3.5 py-2.5 shadow-sm border {{ $entry['is_reply'] ? 'bg-[#1E4630] text-white border-[#143021]' : 'bg-[#F7F4EB] text-[#2B1A11] border-[#EBE3D3]' }}">
                                        <div class="flex items-center justify-between gap-3 mb-1">
                                            <p class="text-[10px] font-black uppercase tracking-wide {{ $entry['is_reply'] ? 'text-emerald-200' : 'text-[#8A441B]' }}">
                                                {{ $entry['is_reply'] ? 'Middo Support' : 'You' }}
                                            </p>
                                            <time class="text-[10px] font-semibold {{ $entry['is_reply'] ? 'text-emerald-100/80' : 'text-gray-400' }}">
                                                {{ \Carbon\Carbon::parse($entry['created_at'])->timezone('Asia/Dhaka')->format('M d · g:i A') }}
                                            </time>
                                        </div>

                                        @if(!$entry['is_reply'] && !empty($entry['category']) && $loop->first)
                                            <p class="text-[10px] font-bold mb-1 {{ $entry['is_reply'] ? 'text-emerald-100' : 'text-[#635347]' }}">
                                                {{ $this->categoryLabel($entry['category']) }}
                                            </p>
                                        @endif

                                        <p class="text-sm font-semibold leading-relaxed whitespace-pre-line">{{ $entry['message'] }}</p>

                                        @if(!empty($entry['attachment']))
                                            <a href="{{ asset($entry['attachment']) }}" target="_blank" rel="noopener noreferrer"
                                               class="mt-2 block rounded-xl overflow-hidden border {{ $entry['is_reply'] ? 'border-emerald-700' : 'border-[#EBE3D3]' }}">
                                                <img src="{{ asset($entry['attachment']) }}" alt="Attachment" class="max-h-40 w-full object-cover">
                                            </a>
                                        @endif

                                        <p class="text-[10px] font-semibold mt-1.5 {{ $entry['is_reply'] ? 'text-emerald-100/70' : 'text-gray-400' }}">
                                            {{ $entry['author_name'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!$hasExistingComplaint)
                        <div class="space-y-4 pt-1">
                            <div>
                                <label for="complaint-category" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-2">Issue Category</label>
                                <select id="complaint-category" wire:model="category"
                                        class="w-full rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-3 py-2.5 text-sm font-semibold text-[#2B1A11] focus:outline-none focus:ring-2 focus:ring-middo-orange/30">
                                    <option value="delivery">Delivery Issue</option>
                                    <option value="food_quality">Food Quality</option>
                                    <option value="payment">Payment Issue</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('category') <span class="text-red-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="complaint-message" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-2">Message</label>
                                <textarea id="complaint-message" wire:model="message" rows="4"
                                          placeholder="Describe your issue or support request..."
                                          class="w-full rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-3 py-2.5 text-sm font-semibold text-[#2B1A11] placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-middo-orange/30 resize-none"></textarea>
                                @error('message') <span class="text-red-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="complaint-attachment" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-2">Attach Image (optional, max 2MB)</label>
                                <input id="complaint-attachment" type="file" wire:model="attachment" accept="image/*"
                                       class="block w-full text-xs font-semibold text-[#635347] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#F7F4EB] file:text-[#2B1A11] file:font-bold hover:file:bg-[#EFE9DC]">
                                <div wire:loading wire:target="attachment" class="text-[11px] font-semibold text-[#635347] mt-1">Uploading image...</div>
                                @error('attachment') <span class="text-red-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror

                                @if($attachment)
                                    <div class="mt-2 rounded-xl overflow-hidden border border-[#EBE3D3]">
                                        <img src="{{ $attachment->temporaryUrl() }}" alt="Preview" class="max-h-40 w-full object-cover">
                                    </div>
                                @endif
                            </div>
                        </div>
                    @elseif($complaintResolved)
                        <div class="bg-emerald-50 border border-emerald-200/80 rounded-xl p-4 text-sm font-semibold text-emerald-900 leading-relaxed">
                            This complaint is marked complete. You can still read the conversation above.
                        </div>
                    @else
                        <div class="space-y-3 pt-1 border-t border-[#EBE3D3]">
                            <label for="complaint-reply" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight">Your reply</label>
                            <textarea id="complaint-reply" wire:model="message" rows="3"
                                      placeholder="Add a follow-up message…"
                                      class="w-full rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-3 py-2.5 text-sm font-semibold text-[#2B1A11] placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-middo-orange/30 resize-none"></textarea>
                            @error('message') <span class="text-red-500 text-xs font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>

                <div class="p-5 border-t border-[#EBE3D3] shrink-0 flex gap-3">
                    @if(!$hasExistingComplaint)
                        <button type="button" wire:click="closeModal"
                                class="flex-1 py-3 rounded-xl border border-[#EBE3D3] text-sm font-bold text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] transition">
                            Cancel
                        </button>
                        <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,attachment"
                                class="flex-1 py-3 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-black transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit,attachment">Submit Request</span>
                            <span wire:loading wire:target="submit,attachment">Submitting...</span>
                        </button>
                    @elseif($complaintResolved)
                        <button type="button" wire:click="closeModal"
                                class="w-full py-3 rounded-xl bg-[#1E4630] hover:bg-[#143021] text-white text-sm font-black transition">
                            Close
                        </button>
                    @else
                        <button type="button" wire:click="closeModal"
                                class="flex-1 py-3 rounded-xl border border-[#EBE3D3] text-sm font-bold text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] transition">
                            Close
                        </button>
                        <button type="button" wire:click="reply" wire:loading.attr="disabled" wire:target="reply"
                                class="flex-1 py-3 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-black transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="reply">Post reply</span>
                            <span wire:loading wire:target="reply">Posting...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
