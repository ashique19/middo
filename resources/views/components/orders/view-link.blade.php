@props([
    'orderId',
    'compact' => false,
    'lens' => null,
])

@php
    $href = \App\Support\StaffOrderRoutes::show((int) $orderId, $lens);
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'inline-flex items-center justify-center font-bold transition shrink-0',
        'px-2.5 py-1 rounded-lg text-[11px] bg-middo-orange text-white hover:bg-[#733614]' => $compact,
        'px-3 py-1.5 rounded-xl text-xs bg-middo-orange text-white hover:bg-[#733614] shadow-sm' => ! $compact,
    ]) }}
>
    View
</a>
