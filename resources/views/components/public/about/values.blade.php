<section class="py-16 bg-[#f5e9dc]">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @php
                $values = [
                    ['title' => 'CULINARY EXCELLENCE', 'tag' => 'EXCELLENCE', 'bg' => 'bg-[#2A3B31]', 'points' => ['Rigorous Kitchen Vetting', 'Chef Certifications', 'Menu Standardization'], 'img' => 'about-1.jpg'],
                    ['title' => 'UNCOMPROMISING SAFETY', 'tag' => 'SAFETY', 'bg' => 'bg-[#C9621F]', 'points' => ['Regular Audits', 'Traceable Ingredients', 'Hygiene Audits'], 'img' => 'about-2.jpg'],
                    ['title' => 'RELIABLE PARTNERSHIP', 'tag' => 'RELIABILITY', 'bg' => 'bg-[#A88B67]', 'points' => ['On-Time Delivery', 'Simplified Billing', 'Dedicated Support'], 'img' => 'about-3.jpg']
                ];
            @endphp

            @foreach($values as $v)
                <div class="bg-white rounded-3xl border border-black shadow-sm flex flex-col overflow-hidden">
                    <div class="relative w-full h-64 border-b border-black">
                        <img src="{{ asset('img/public/'.$v['img']) }}" alt="{{ $v['title'] }}" class="w-full h-full object-cover">
                        
                        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2">
                            <span class="{{ $v['bg'] }} text-white font-bold px-5 py-1.5 rounded-full text-sm border border-black shadow-md">
                                {{ $v['tag'] }}
                            </span>
                        </div>
                    </div>

                    <div class="text-center p-6 bg-middo-cream">
                        <h3 class="text-xl font-bold mb-4">{{ $v['title'] }}</h3>
                        <ul class="text-left space-y-2 inline-block">
                            @foreach($v['points'] as $point)
                                <li class="flex items-center gap-2 text-gray-700">
                                    <span class="w-2 h-2 bg-[#C9621F] rounded-full"></span> {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>