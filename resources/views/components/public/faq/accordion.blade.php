<section class="py-16 bg-[#F5F2E9]">
    <div class="max-w-4xl mx-auto px-6">
        @php
            $faqGroups = [
                'GENERAL FAQs' => [
                    ['q' => 'What is Middo\'s core mission?', 'a' => 'To provide quality-assured corporate catering with zero compromise.'],
                ],
                'CORPORATE INDIVIDUAL Q&A' => [
                    ['q' => 'Is the individual pre-paid wallet balance refundable?', 'a' => 'Yes, you can easily withdraw your full wallet balance to your linked payment method at any time before it\'s used for an order.'],
                    ['q' => 'Can I really remove a scheduled meal date up to an hour before dispatch?', 'a' => 'Absolutely. Our \'Active Orders\' slide-to-edit feature gives you full flexibility up to one hour prior to the kitchen dispatch deadline.'],
                    ['q' => 'What is "Middo Box traceability"?', 'a' => 'Each thermal Middo Box is uniquely tracked for temperature and position, ensuring your gourmet meal arrives perfectly preserved to your desk.'],
                    ['q' => 'What happens if I miss my desk-side delivery?', 'a' => 'Your dedicated Rider will leave a secure delivery card. We\'ll also notify you, and your meal can be re delivered or held for a specified time.']
                ],
                'KITCHEN PARTNER (KITCHEN) Q&A' => [
                    ['q' => 'Are there any hidden fees or charges for using the platform?', 'a' => 'Middo is entirely transparent. Our only fees are clearly listed commissions, and the \'Middo Box\' subscription is included.'],
                    ['q' => 'What makes the "Middo Box" essential for my operations?', 'a' => 'The propriety Box locks in temperature and ensures quality. Its integrated tracking data helps optimize your delivery logistics.'],
                    ['q' => 'What if a rider is significantly late to pick up orders?', 'a' => 'We monitor rider performance, and our system automatically flags delays. Our support will intervene, and you are not responsible for delivery time overages.'],
                    ['q' => 'How do I request more "Middo Boxes" when stock is low?', 'a' => 'Easily request replenishments directly within your Dashboard. Your low-stock status is automated, making re-ordering seamless.']
                ],
            ];
        @endphp

        @foreach($faqGroups as $group => $questions)
            <div class="mb-10">
                <h3 class="text-xl font-extrabold text-[#2D2D2D] mb-4 uppercase tracking-wider">{{ $group }}</h3>
                
                <div class="space-y-4">
                    @foreach($questions as $index => $item)
                        <div x-data="{ open: false }" class="bg-white rounded-lg shadow-sm border border-gray-200">
                            <button @click="open = !open" class="w-full flex items-center justify-between p-4 text-left">
                                <div class="flex items-center gap-4">
                                    <div class="w-8 h-8 rounded-full bg-[#C9621F] flex items-center justify-center text-white font-bold shrink-0">Q</div>
                                    <span class="font-bold text-[#2D2D2D] leading-snug">{{ $item['q'] }}</span>
                                </div>
                                <svg :class="open ? 'rotate-180' : ''" class="w-5 h-5 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="open" x-cloak class="px-4 pb-4 pt-0">
                                <div class="flex items-center gap-4 border-t border-gray-100 pt-3">
                                    <div class="w-8 h-8 rounded-full bg-[#2A3B31] flex items-center justify-center text-white font-bold shrink-0">A</div>
                                    <p class="text-gray-700">{{ $item['a'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>