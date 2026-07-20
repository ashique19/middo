@props([
    'label' => 'Package',
    'title' => null,
])

<span
    {{ $attributes->class('inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-sky-100 text-sky-800 border border-sky-200') }}
    @if($title) title="{{ $title }}" @endif
>
    {{ $label }}
</span>
