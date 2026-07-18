@props([
    'viewMode' => 'default',
    'exportable' => false,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <div class="inline-flex rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
        <button
            type="button"
            wire:click="setViewMode('default')"
            @class([
                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                'bg-middo-orange text-white' => $viewMode !== 'list',
                'text-gray-600 hover:bg-gray-50' => $viewMode === 'list',
            ])>
            Default
        </button>
        <button
            type="button"
            wire:click="setViewMode('list')"
            @class([
                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                'bg-middo-orange text-white' => $viewMode === 'list',
                'text-gray-600 hover:bg-gray-50' => $viewMode !== 'list',
            ])>
            List view
        </button>
    </div>

    @if($exportable)
        <button
            type="button"
            wire:click="exportExcel"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition disabled:opacity-60">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5L16.5 12M12 3v13.5" />
            </svg>
            <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
            <span wire:loading wire:target="exportExcel">Exporting…</span>
        </button>
    @endif
</div>
