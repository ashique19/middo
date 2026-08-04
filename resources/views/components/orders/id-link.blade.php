@props([
    'orderId',
    'lens' => null,
])

@php
    $href = \App\Support\StaffOrderRoutes::show((int) $orderId, $lens);
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class('font-mono font-bold text-middo-dark hover:text-middo-orange transition') }}
>
    #{{ $orderId }}
</a>
