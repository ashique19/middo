<x-layouts.public.app>
    <x-public.menu.featured-menu :menu="$menu" />
    
    {{-- Mount the checkout workflow globally on the page layout shell --}}
    <livewire:public.order-checkout-modal />
</x-layouts.public.app>