@props(['compact' => false])

@php
    $roleName = auth()->user()?->role?->name;
    $alertsRoute = match ($roleName) {
        'admin' => 'admin.alerts.index',
        'operation' => 'operation.alerts.index',
        'kitchen' => 'kitchen.alerts',
        'delivery' => 'delivery.alerts',
        default => null,
    };
    $alertsHref = ($alertsRoute && \Illuminate\Support\Facades\Route::has($alertsRoute))
        ? route($alertsRoute)
        : null;
    $unreadAlerts = $alertsHref
        ? \App\Support\StaffAlerts::unreadCount((int) auth()->id())
        : 0;
@endphp

@if($alertsHref)
    <a href="{{ $alertsHref }}"
       {{ $attributes->class([
           'relative inline-flex items-center justify-center shrink-0 transition',
           $compact
               ? 'w-11 h-11 rounded-2xl border border-[#E5DCC8] bg-white/80 text-[#2B1A11]'
               : 'w-10 h-10 rounded-full border border-gray-200 text-middo-dark hover:border-middo-orange hover:text-middo-orange',
       ]) }}
       aria-label="Alerts{{ $unreadAlerts > 0 ? ' ('.$unreadAlerts.' unread)' : '' }}"
       title="Alerts">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if($unreadAlerts > 0)
            <span class="absolute -top-1 -right-1 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-middo-orange text-white text-[10px] font-black grid place-items-center leading-none">
                {{ $unreadAlerts > 9 ? '9+' : $unreadAlerts }}
            </span>
        @endif
    </a>
@endif
