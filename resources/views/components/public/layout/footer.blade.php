<footer class="bg-[#1A2D22] text-white py-16">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-12 mb-12">
            
            <div class="space-y-4">
                <img src="{{ asset('img/settings/logo.png') }}" alt="Middo" class="h-10 w-auto">
                <p class="text-gray-400 text-sm leading-relaxed">
                    Elevating office dining through quality, trust, and curated local culinary excellence.
                </p>
                
                <div class="flex gap-4">
                    <a href="#" class="text-gray-400 hover:text-[#C9621F] transition duration-300">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874V12h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-[#C9621F] transition duration-300">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452z"/></svg>
                    </a>
                </div>
            </div>

            <div>
                <h4 class="font-bold mb-6 text-lg">Platform</h4>
                <ul class="space-y-3 text-gray-400">
                    <li><a href="{{ route('home') }}" class="hover:text-white transition">Home</a></li>
                    <li><a href="{{ route('how-it-works-corporates') }}" class="hover:text-white transition">For Corporates</a></li>
                    <li><a href="{{ route('how-it-works-kitchen') }}" class="hover:text-white transition">For Kitchens</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-white transition">About Us</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold mb-6 text-lg">Support</h4>
                <ul class="space-y-3 text-gray-400">
                    <li><a href="{{ route('faq') }}" class="hover:text-white transition">FAQ</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Contact Us</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold mb-6 text-lg">Legal</h4>
                <ul class="space-y-3 text-gray-400">
                    <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-white transition">Terms & Conditions</a></li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500 gap-4">
            <p>&copy; {{ date('Y') }} Middo. All rights reserved.</p>
            <div class="flex gap-6">
                <span>Dhaka, Bangladesh</span>
            </div>
        </div>
    </div>
</footer>