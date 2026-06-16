<section class="py-20 bg-[#F5F2E9] relative">
    
    <div class="absolute inset-0 z-0 opacity-20" 
         style="background-image: url('{{ asset('img/public/how-it-works-corporates.jpg') }}'); background-repeat: repeat;">
    </div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">
        
        <div class="text-center mb-16">
            <h2 class="text-4xl md:text-5xl font-extrabold text-[#2D2D2D] mb-4">How Middo works for You</h2>
            <p class="text-xl text-[#2D2D2D]/80 font-medium">Premium Meals, Personal Choice, Zero Hassle.</p>
        </div>

        <svg class="absolute top-[250px] left-0 w-full h-[150px] pointer-events-none hidden md:block" preserveAspectRatio="none">
            <path d="M 180 20 C 250 20, 250 120, 320 120 C 390 120, 390 20, 460 20 C 530 20, 530 120, 600 120 C 670 120, 670 20, 740 20" 
                  stroke="#A88B67" stroke-width="6" fill="none" stroke-linecap="round" stroke-dasharray="10 10"/>
        </svg>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative z-10">
            
            <div class="bg-[#FDFBF7] bg-middo-cream p-6 rounded-3xl border-2 border-[#EADFC8] shadow-lg flex flex-col items-center text-center">
                <img src="{{ asset('img/public/how-it-works-card-1.png') }}" alt="Discover" class="rounded-2xl mb-6 w-full h-48 object-cover">
                <span class="text-[#A88B67] font-bold text-sm uppercase tracking-wider">Step 1:</span>
                <h3 class="text-xl font-bold mt-1 mb-3">Discover & Select.</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Browse curated menus tailored for corporate professionals. Use the B2B Menu Tree and filters to find your perfect daily meal.</p>
            </div>

            <div class="bg-[#FDFBF7] bg-middo-cream p-6 rounded-3xl border-2 border-[#EADFC8] shadow-lg flex flex-col items-center text-center">
                <img src="{{ asset('img/public/how-it-works-card-2.jpg') }}" alt="Schedule" class="rounded-2xl mb-6 w-full h-48 object-cover">
                <span class="text-[#A88B67] font-bold text-sm uppercase tracking-wider">Step 2:</span>
                <h3 class="text-xl font-bold mt-1 mb-3">Schedule & Order.</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Schedule your week or pre-book for the entire month. Pre-fund your secure Middo Balance using the Add Money interface.</p>
            </div>

            <div class="bg-[#FDFBF7] bg-middo-cream p-6 rounded-3xl border-2 border-[#EADFC8] shadow-lg flex flex-col items-center text-center">
                <img src="{{ asset('img/public/how-it-works-card-3.jpg') }}" alt="Delivery" class="rounded-2xl mb-6 w-full h-48 object-cover">
                <span class="text-[#A88B67] font-bold text-sm uppercase tracking-wider">Step 3:</span>
                <h3 class="text-xl font-bold mt-1 mb-3">Seamless Delivery.</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Meals are packed in our thermal Middo Boxes, which lock-in temperature and guarantee traceability. Our assigned Rider ensures desk-side delivery.</p>
            </div>

            <div class="bg-[#FDFBF7] bg-middo-cream p-6 rounded-3xl border-2 border-[#EADFC8] shadow-lg flex flex-col items-center text-center">
                <img src="{{ asset('img/public/how-it-works-card-4.jpg') }}" alt="Rate" class="rounded-2xl mb-6 w-full h-48 object-cover">
                <span class="text-[#A88B67] font-bold text-sm uppercase tracking-wider">Step 4:</span>
                <h3 class="text-xl font-bold mt-1 mb-3">Rate & Review.</h3>
                <p class="text-gray-600 text-sm leading-relaxed">Enjoy your lunch break. Use the focused Order Feedback modal to quickly rate and review your meal, helping us maintain gourmet standards.</p>
            </div>
        </div>

        <div class="text-center mt-16">
            <a href="{{ route('register') }}" class="inline-block bg-middo-orange text-white font-bold px-10 py-4 rounded-full text-lg hover:bg-[#8e7557] transition-all shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
                Create Your Middo Account
            </a>
        </div>

    </div>
    
</section>