<section class="py-16 bg-[#f5e9dc]">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex flex-col md:flex-row items-center gap-12">
            
            <div class="md:w-1/3">
                <h2 class="text-4xl font-extrabold text-[#2D2D2D] leading-tight">THE TEAM BEHIND YOUR LUNCH</h2>
            </div>

            <div class="md:w-2/3 grid grid-cols-2 md:grid-cols-4 gap-6">
                @php
                    $team = [
                        ['role' => 'Data analyst', 'img' => 'about-10.png'],
                        ['role' => 'Logistics coordinator', 'img' => 'about-11.png'],
                        ['role' => 'Quality control manager', 'img' => 'about-12.png'],
                        ['role' => 'Client relations specialist', 'img' => 'about-13.png'],
                    ];
                @endphp

                @foreach($team as $member)
                    <div class="flex flex-col items-center text-center">
                        <img src="{{ asset('img/public/'.$member['img']) }}" alt="{{ $member['role'] }}" class="w-24 h-24 mb-4 object-contain">
                        <h4 class="font-bold text-gray-800">{{ $member['role'] }}</h4>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>