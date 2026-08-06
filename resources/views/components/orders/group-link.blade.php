@props([
    'groupId' => null,
    'name' => null,
])

@php
    $label = $name ?: '—';
    $href = $groupId ? \App\Support\StaffOrderGroupRoutes::show((int) $groupId) : null;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class('text-middo-orange hover:underline font-semibold') }}>
        {{ $label }}
    </a>
@else
    <span {{ $attributes->class('font-semibold text-middo-orange') }}>{{ $label }}</span>
@endif
