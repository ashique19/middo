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
