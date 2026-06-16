<section class="py-20 bg-[#2A3B31] text-[#EADFC8]">
    <div class="max-w-7xl mx-auto px-6">
        
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-6">FOR KITCHENS: JOIN THE PREMIER OFFICE CATERING NETWORK</h1>
            <p class="text-xl max-w-3xl mx-auto opacity-90">Middo connects your high-quality kitchen with exclusive corporate clients for high-volume, reliable lunch orders. Join our network, simplify your corporate business, and focus on what you do best: cooking.</p>
        </div>

        <div class="bg-[#3D5246] p-8 md:p-10 rounded-3xl mb-10 border border-[#526D5E]">
            <h2 class="text-3xl font-bold mb-8 text-center">STREAMLINED ORDER MANAGEMENT</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start"> <div class="bg-[#2A3B31] p-6 rounded-2xl border border-[#526D5E] text-center">
                    <img src="{{ asset('img/public/how-it-works-kitchen-1.png') }}" alt="Order Management" class="mx-auto mb-4 w-full h-auto">
                    <p class="font-semibold text-lg">Efficient workflow from acceptance to dispatch.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach([
                        ['title' => 'PREDICTABLE VOLUME', 'desc' => 'Secure large, scheduled lunch orders in advance.', 'img' => 'img/public/how-it-works-kitchen-3.png'],
                        ['title' => 'SIMPLIFIED FULFILLMENT', 'desc' => 'Consolidated bulk order data, clear dish lists.', 'img' => 'img/public/how-it-works-kitchen-4.png'],
                        ['title' => 'INTEGRATED INVOICING', 'desc' => 'Automated, consolidated billing and reporting.', 'img' => 'img/public/how-it-works-kitchen-5.png']
                    ] as $feature)
                        <div class="text-center p-2 flex flex-col items-center">
                            <div class="h-16 w-16 mb-4 flex items-center justify-center shrink-0">
                                <img src="{{ asset($feature['img']) }}" alt="{{ $feature['title'] }}" class="max-h-16 max-w-full object-contain">
                            </div>
                            <h4 class="font-bold text-sm mb-2 uppercase leading-tight">{{ $feature['title'] }}</h4>
                            <p class="text-xs opacity-80 leading-relaxed">{{ $feature['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-[#3D5246] p-8 md:p-10 rounded-3xl border border-[#526D5E]">
            <h2 class="text-3xl font-bold mb-8 text-center">MARKETING & REVENUE BOOST</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
                <div class="bg-[#2A3B31] p-6 rounded-2xl border border-[#526D5E] text-center">
                    <img src="{{ asset('img/public/how-it-works-kitchen-2.png') }}" alt="Revenue Growth" class="mx-auto mb-4 w-full h-auto">
                    <p class="font-semibold text-lg">Reach new heights and stabilize income.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach([
                        ['title' => 'EXCLUSIVE AUDIENCE', 'desc' => 'Access premium corporate clients directly.', 'img' => 'img/public/how-it-works-kitchen-6.png'],
                        ['title' => 'PERFORMANCE INCENTIVES', 'desc' => 'Earn higher commissions based on kitchen ratings.', 'img' => 'img/public/how-it-works-kitchen-7.png'],
                        ['title' => 'GUARANTEED PAYMENTS', 'desc' => 'Secure and timely weekly payments via the platform.', 'img' => 'img/public/how-it-works-kitchen-8.png']
                    ] as $feature)
                        <div class="text-center p-2 flex flex-col items-center">
                            <div class="h-16 w-16 mb-4 flex items-center justify-center shrink-0">
                                <img src="{{ asset($feature['img']) }}" alt="{{ $feature['title'] }}" class="max-h-16 max-w-full object-contain">
                            </div>
                            <h4 class="font-bold text-sm mb-2 uppercase leading-tight">{{ $feature['title'] }}</h4>
                            <p class="text-xs opacity-80 leading-relaxed">{{ $feature['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>