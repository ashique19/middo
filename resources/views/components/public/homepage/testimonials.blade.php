<section class="py-20 bg-middo-cream" x-data="{ 
    active: 0, 
    testimonials: [
        { quote: 'Middo transformed our team lunches! The quality is consistently excellent, and the variety keeps everyone happy.', author: 'Sarah J, HR Manager' },
        { quote: 'The best decision for our office culture. Reliable and delicious.', author: 'Mark T, Operations' }
    ] 
}">
    <div class="max-w-4xl mx-auto px-6">
        <h2 class="text-3xl md:text-4xl font-bold text-center mb-16 text-middo-dark">Testimonials</h2>
        
        <div class="relative flex items-center justify-between gap-4 md:gap-8">
            <button @click="active = (active === 0) ? testimonials.length - 1 : active - 1" 
                    class="p-2 md:p-3 rounded-full border border-gray-200 bg-white hover:bg-gray-50 transition shrink-0 z-20">
                &larr;
            </button>
            
            <div class="flex-1 flex flex-col md:flex-row items-center gap-8 md:gap-12 relative h-64 md:h-40">
                
                <div class="flex-1 text-left relative w-full h-full">
                    <div class="text-middo-orange mb-4 w-8 h-8">
                        <svg viewBox="0 0 640 640" fill="currentColor">
                            <path d="M96 280C96 213.7 149.7 160 216 160L224 160C241.7 160 256 174.3 256 192C256 209.7 241.7 224 224 224L216 224C185.1 224 160 249.1 160 280L160 288L224 288C259.3 288 288 316.7 288 352L288 416C288 451.3 259.3 480 224 480L160 480C124.7 480 96 451.3 96 416L96 280zM352 280C352 213.7 405.7 160 472 160L480 160C497.7 160 512 174.3 512 192C512 209.7 497.7 224 480 224L472 224C441.1 224 416 249.1 416 280L416 288L480 288C515.3 288 544 316.7 544 352L544 416C544 416.3 515.3 480 480 480L416 480C380.7 480 352 416.3 352 416L352 280z"/>
                        </svg>
                    </div>
                    
                    <div class="relative w-full h-full">
                        <template x-for="(t, index) in testimonials" :key="index">
                            <div x-show="active === index" 
                                 x-transition:enter="transition ease-out duration-500"
                                 x-transition:enter-start="opacity-0 translate-x-4"
                                 x-transition:enter-end="opacity-100 translate-x-0"
                                 x-transition:leave="transition ease-in duration-300"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="absolute top-0 left-0 w-full">
                                <p class="text-lg md:text-xl text-middo-dark font-medium leading-relaxed mb-4" x-text="t.quote"></p>
                                <p class="font-bold text-middo-dark text-lg" x-text="t.author"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="hidden md:flex items-center justify-center shrink-0 w-48">
                    <img src="{{ asset('img/settings/logo.png') }}" alt="Middo" class="h-12 w-auto opacity-80">
                </div>
            </div>

            <button @click="active = (active === testimonials.length - 1) ? 0 : active + 1" 
                    class="p-2 md:p-3 rounded-full border border-gray-200 bg-white hover:bg-gray-50 transition shrink-0 z-20">
                &rarr;
            </button>
        </div>

        <div class="flex justify-center space-x-2 mt-12">
            <template x-for="(t, index) in testimonials" :key="index">
                <button @click="active = index" 
                        :class="active === index ? 'bg-middo-orange w-8' : 'bg-gray-300 w-3'" 
                        class="h-3 rounded-full transition-all duration-300"></button>
            </template>
        </div>
    </div>
</section>