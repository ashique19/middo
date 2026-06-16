<section class="py-20 bg-middo-cream">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-4xl font-extrabold text-[#2D2D2D] mb-12 text-center">THE MIDDO CERTIFICATION PROCESS</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            @php
                $steps = [
                    ['step' => 'Step 1: Application & Site Visit', 'desc' => 'Kitchen application and initial hygiene visit.', 'img' => 'about-9.png'],
                    ['step' => 'Step 2: Stringent Audits', 'desc' => 'Detailed inspection of ingredient sources, safety protocols, and staff training.', 'img' => 'about-8.png'],
                    ['step' => 'Step 3: Ongoing Monitoring', 'desc' => 'Regular unannounced checks and performance metrics.', 'img' => 'about-7.png']
                ];
            @endphp

            @foreach($steps as $step)
                <div class="bg-[#C9621F] rounded-3xl p-6 text-white border border-black shadow-sm overflow-hidden flex flex-col">
                    <img src="{{ asset('img/public/'.$step['img']) }}" alt="{{ $step['step'] }}" class="w-full h-56 object-cover rounded-2xl mb-6 border border-black">
                    <h4 class="font-bold text-lg mb-2">{{ $step['step'] }}</h4>
                    <p class="text-white/90 text-sm leading-relaxed">{{ $step['desc'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#2A3B31] text-white p-8 rounded-3xl border border-black flex flex-col justify-center">
                <img src="{{ asset('img/public/about-6.png') }}" alt="Certified Kitchen" class="w-full h-64 object-cover rounded-2xl mb-6 border border-black">
                <h3 class="text-2xl font-bold mb-2">Step 4: Certified Partner</h3>
                <p class="text-white/90">Only fully compliant kitchens are listed.</p>
            </div>

            <div class="bg-[#C9621F] p-8 rounded-3xl border border-black flex items-center justify-center">
                <img src="{{ asset('img/public/about-4.png') }}" alt="Middo Certified" class="w-full max-w-sm rounded-xl">
            </div>
        </div>
    </div>
</section>