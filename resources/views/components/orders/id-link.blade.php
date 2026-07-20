@props([
    'orderId',
])

@php
    $href = \App\Support\StaffOrderRoutes::show((int) $orderId);
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class('font-mono font-bold text-middo-dark hover:text-middo-orange transition') }}
>
    #{{ $orderId }}
</a>
