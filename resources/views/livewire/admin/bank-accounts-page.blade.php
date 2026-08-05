<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">Bank accounts</h1>
            <p class="text-sm text-gray-500 mt-1">Middo multi-bank float. Accounts posts EPS settlements and adjustments on the bank ledger.</p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold hover:bg-[#733614]">
            Add account
        </button>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @if($showForm)
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">{{ $editingId ? 'Edit account' : 'New account' }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Label</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Bank</label>
                    <input type="text" wire:model="bank_name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('bank_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Account number</label>
                    <input type="text" wire:model="account_number" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Branch</label>
                    <input type="text" wire:model="branch" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" wire:model="is_default" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                    Default for EPS settlements
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                    Active
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="save" class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Save</button>
                <button type="button" wire:click="closeForm" class="inline-flex px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="p-4">Account</th>
                    <th class="p-4">Bank</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($accounts as $account)
                    <tr wire:key="bank-{{ $account->id }}">
                        <td class="p-4">
                            <div class="font-bold text-middo-dark">{{ $account->name }}</div>
                            <div class="text-xs text-gray-500">{{ $account->account_number ?: '—' }} @if($account->branch) · {{ $account->branch }} @endif</div>
                        </td>
                        <td class="p-4 text-gray-700">{{ $account->bank_name }}</td>
                        <td class="p-4">
                            @if($account->is_default)
                                <span class="inline-flex text-[10px] font-black uppercase px-1.5 py-0.5 rounded bg-sky-100 text-sky-800">Default</span>
                            @endif
                            <span @class([
                                'inline-flex text-[10px] font-black uppercase px-1.5 py-0.5 rounded',
                                'bg-emerald-100 text-emerald-800' => $account->is_active,
                                'bg-gray-100 text-gray-500' => ! $account->is_active,
                            ])>{{ $account->is_active ? 'Active' : 'Off' }}</span>
                        </td>
                        <td class="p-4 text-right space-x-2">
                            <button type="button" wire:click="openEdit({{ $account->id }})" class="text-xs font-bold text-middo-orange hover:underline">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $account->id }})" class="text-xs font-bold text-gray-600 hover:underline">{{ $account->is_active ? 'Deactivate' : 'Activate' }}</button>
                            <button type="button" wire:click="deleteAccount({{ $account->id }})" wire:confirm="Delete this bank account?" class="text-xs font-bold text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-10 text-center text-gray-400 italic">No bank accounts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($accounts->hasPages())
        <div>{{ $accounts->links() }}</div>
    @endif
</div>
