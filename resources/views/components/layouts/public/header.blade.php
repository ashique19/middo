<header class="bg-[#FDFBF7] shadow-sm sticky top-0 z-50 antialiased font-sans" x-data="{ mobileMenuOpen: false }">
    
    {{-- DESKTOP NAVIGATION ROW (Hidden on Mobile) --}}
    <x-layouts.public.header-desktop />

    {{-- MOBILE NAVIGATION ROW & SLIDE DRAWER (Hidden on Desktop) --}}
    <x-layouts.public.header-mobile />

</header>