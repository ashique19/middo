<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Settings</h1>
        <p class="text-sm text-gray-500 mt-1">
            Meal grouping, kitchen capacity, and rider commission defaults for non-lunch runs.
            Lunch kitchen→corporate commission stays on each menu item.
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

    <form wire:submit="save" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-8">
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Meal grouping</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                        Auto group max quantity
                    </label>
                    <input type="number" min="1" max="500" wire:model="auto_group_quantity"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('auto_group_quantity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">Max total meals in one Middo order group.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                        Full prepay from meal quantity
                    </label>
                    <input type="number" min="1" max="100" wire:model="full_prepay_from_active_orders"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('full_prepay_from_active_orders') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">
                        At this many meals (sum of quantities on active orders + this cart, same day or across days), require 100% prepayment.
                        Cash on Delivery stays available below this number (default 3 → COD for 1–2 meals only).
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                        Accept window (minutes)
                    </label>
                    <input type="number" min="1" max="10080" wire:model="accept_window_minutes"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('accept_window_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">How long before delivery kitchens may accept a group.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                        Accept window warn (minutes)
                    </label>
                    <input type="number" min="1" max="10080" wire:model="accept_window_warn_minutes"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('accept_window_warn_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">Warn kitchens this many minutes before the accept window closes.</p>
                </div>
            </div>
        </section>

        <section class="space-y-4 border-t border-gray-100 pt-6">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Kitchen tier defaults</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Default allowed concurrent open groups. Copied onto a kitchen when it is activated (if not already set). Changing these does not overwrite existing kitchens.
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Silver</label>
                    <input type="number" min="0" max="100" wire:model="tier_silver"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('tier_silver') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Gold</label>
                    <input type="number" min="0" max="100" wire:model="tier_gold"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('tier_gold') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Platinum</label>
                    <input type="number" min="0" max="100" wire:model="tier_platinum"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('tier_platinum') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 border-t border-gray-100 pt-6">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Rider commissions (box & custom)</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Default ৳ per box / per run. Credited on run start. Per-rider overrides on the delivery user profile.
                    Lunch deliveries use <span class="font-semibold text-middo-dark">menu delivery commission</span>, not these fields.
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Corporate → kitchen (box)</label>
                    <input type="number" min="0" wire:model="commission_corporate_to_kitchen"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('commission_corporate_to_kitchen') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Kitchen → ops (box)</label>
                    <input type="number" min="0" wire:model="commission_kitchen_to_ops"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('commission_kitchen_to_ops') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="kitchen_to_ops_via_rider"
                               class="mt-1 rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                        <span>
                            <span class="block text-sm font-bold text-middo-dark">Kitchen → ops via rider</span>
                            <span class="block text-[11px] text-gray-400 mt-0.5">When on, kitchen can assign a rider for empty-box returns (books the kitchen→ops rate). Direct warehouse send stays available. Default off.</span>
                        </span>
                    </label>
                    @error('kitchen_to_ops_via_rider') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Ops → kitchen (box)</label>
                    <input type="number" min="0" wire:model="commission_ops_to_kitchen"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('commission_ops_to_kitchen') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Custom point → point</label>
                    <input type="number" min="0" wire:model="commission_custom"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('commission_custom') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Mid-run rescue (optional)</label>
                    <input type="number" min="0" wire:model="commission_mid_run_rescue"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    <p class="text-[11px] text-gray-400 mt-1">Paid to rescue rider B only. Starter keeps lunch commission. Default ৳0.</p>
                    @error('commission_mid_run_rescue') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Finance — food VAT</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Inclusive VAT on food only (not charges). Snapshotted onto each order at place time; Middo rest uses food ex-VAT.
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">VAT rate (%)</label>
                    <input type="number" min="0" max="100" step="0.01" wire:model="vat_rate_pct"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    <p class="text-[11px] text-gray-400 mt-1">Default 5% for food business. Admin editable.</p>
                    @error('vat_rate_pct') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">EPS gateway fees (%)</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Percent of gross charged by EPS sub-gateway. Bank ledger credits net (gross − fee) on successful payments.
                </p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                @foreach([
                    'eps_fee_bank' => 'Bank',
                    'eps_fee_bkash' => 'bKash',
                    'eps_fee_nagad' => 'Nagad',
                    'eps_fee_rocket' => 'Rocket',
                    'eps_fee_card' => 'Card',
                    'eps_fee_other' => 'Other',
                ] as $field => $label)
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $label }}</label>
                        <input type="number" min="0" max="100" step="0.01" wire:model="{{ $field }}"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                        @error($field) <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="inline-flex px-5 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition">
                Save settings
            </button>
        </div>
    </form>
</div>
