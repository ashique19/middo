<div wire:key="operation-search-order" class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-bold text-middo-dark">Search Order</h1>
                <p class="text-sm font-semibold text-gray-500">
                    Search by order ID, customer name, mobile, menu, address, or delivery date.
                </p>
            </div>
            <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
        </div>

        <div class="max-w-xl">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Start typing to search orders..."
                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-middo-dark shadow-sm focus:border-middo-orange focus:ring-middo-orange"
            />
        </div>
    </div>

    @if($search !== '')
        <p class="text-sm font-semibold text-gray-500">
            {{ count($orders) }} matching {{ str('order')->plural(count($orders)) }} found.
        </p>

        <x-operation.orders.table
            :orders="$orders"
            empty-message="No orders matched your search." />
    @endif
</div>
