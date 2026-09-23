@props(['url' => null, 'name' => 'Kitchen', 'class' => 'h-10 w-10'])

@php
    $initial = strtoupper(substr(trim($name) !== '' ? trim($name) : 'K', 0, 1));
@endphp

@if($url)
    <img src="{{ $url }}" alt="{{ $name }}" {{ $attributes->merge(['class' => $class.' rounded-full object-cover shrink-0 border border-gray-200']) }}>
@else
    <span {{ $attributes->merge(['class' => $class.' rounded-full bg-middo-orange/10 text-middo-orange grid place-items-center font-black shrink-0']) }}>{{ $initial }}</span>
@endif
