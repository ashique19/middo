<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Cash positions</h1>
        <p class="text-sm text-gray-500 mt-1">
            Where Middo cash sits in the cycle — EPS, kitchen receivable, rider Due, and till — plus bank float.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Cash at EPS</p>
            <p class="font-mono text-2xl font-black text-gray-900 mt-1">৳{{ number_format($snapshot['cash_at_eps']['amount']) }}</p>
            <p class="text-[11px] text-gray-500 mt-2">{{ $snapshot['cash_at_eps']['note'] }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/40 p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-amber-700/70">Recv. kitchen</p>
            <p class="font-mono text-2xl font-black text-amber-950 mt-1">৳{{ number_format($snapshot['cash_receivable_kitchen']['amount']) }}</p>
            <p class="text-[11px] text-amber-900/70 mt-2">{{ $snapshot['cash_receivable_kitchen']['note'] }}</p>
            @if($links['kitchen_money'])
                <a href="{{ $links['kitchen_money'] }}" class="inline-block mt-2 text-xs font-bold text-middo-orange hover:underline">Kitchen money →</a>
            @endif
        </div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50/40 p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-sky-700/70">Recv. riders</p>
            <p class="font-mono text-2xl font-black text-sky-950 mt-1">৳{{ number_format($snapshot['cash_receivable_riders']['amount']) }}</p>
            <p class="text-[11px] text-sky-900/70 mt-2">{{ $snapshot['cash_receivable_riders']['note'] }}</p>
            <div class="flex flex-wrap gap-3 mt-2">
                @if($links['cash_handovers'])
                    <a href="{{ $links['cash_handovers'] }}" class="text-xs font-bold text-middo-orange hover:underline">Handovers →</a>
                @endif
                @if($links['cod_recon'])
                    <a href="{{ $links['cod_recon'] }}" class="text-xs font-bold text-middo-orange hover:underline">COD recon →</a>
                @endif
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700/70">Cash in hand</p>
            <p class="font-mono text-2xl font-black text-emerald-950 mt-1">৳{{ number_format($snapshot['cash_in_hand']['amount']) }}</p>
            <p class="text-[11px] text-emerald-900/70 mt-2">{{ $snapshot['cash_in_hand']['note'] }}</p>
            @if($links['middo_cash'])
                <a href="{{ $links['middo_cash'] }}" class="inline-block mt-2 text-xs font-bold text-middo-orange hover:underline">Cash ledger →</a>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Cash cycle total</h2>
                <p class="text-xs text-gray-500">Kitchen recv + rider Due + till (excludes bank float &amp; EPS).</p>
            </div>
            <p class="font-mono text-3xl font-black text-middo-dark">৳{{ number_format($snapshot['total_cash_cycle']) }}</p>
        </div>
        <div class="flex flex-wrap items-end justify-between gap-3 pt-2 border-t border-gray-100">
            <div>
                <h3 class="text-sm font-bold text-gray-700">Bank float</h3>
                <p class="text-xs text-gray-500">{{ $snapshot['bank_float']['note'] }}</p>
            </div>
            <div class="text-right">
                <p class="font-mono text-xl font-black text-gray-900">৳{{ number_format($snapshot['bank_float']['amount']) }}</p>
                @if($links['bank_ledger'])
                    <a href="{{ $links['bank_ledger'] }}" class="text-xs font-bold text-middo-orange hover:underline">Bank ledger →</a>
                @endif
            </div>
        </div>
    </div>

    @if(count($snapshot['cash_receivable_kitchen']['kitchens'] ?? []) > 0)
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-bold text-middo-dark">Kitchen receivables detail</h2>
            </div>
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Kitchen</th>
                        <th class="p-3 text-right">Owes Middo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($snapshot['cash_receivable_kitchen']['kitchens'] as $row)
                        <tr>
                            <td class="p-3 font-semibold text-gray-800">{{ $row['name'] }}</td>
                            <td class="p-3 text-right font-mono font-bold">৳{{ number_format($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canWrite && $banks->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Deposit till → bank</h2>
            <p class="text-xs text-gray-500">Debits Middo cash till and credits the selected bank account (accounts|admin).</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <select wire:model="depositBankAccountId" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->label() }}</option>
                    @endforeach
                </select>
                <input type="number" min="1" wire:model="depositAmount" placeholder="Amount ৳" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <input type="text" wire:model="depositReason" placeholder="Reason / slip ref" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            </div>
            <button type="button" wire:click="depositTillToBank" wire:confirm="Move this cash from till to bank?"
                    class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold hover:bg-[#733614]">
                Post deposit
            </button>
        </div>
    @elseif($canWrite)
        <div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-400">
            Add a bank account (admin) before posting till→bank deposits.
        </div>
    @endif
</div>
