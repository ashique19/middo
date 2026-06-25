<x-layouts.public.app>
    <div class="min-h-screen bg-middo-cream md:p-8 flex items-center justify-center"
         x-data="{ 
            form: { first_name: '', last_name: '', mobile: '', password: '', address: '', city_id: '', area_id: '' },
            errors: {}, loading: false,
            cityName: 'Select City', areaName: 'Select Area', cityOpen: false, areaOpen: false, areas: [],
            get isMobileValid() { return this.form.mobile.length === 0 || /^01[3-9][0-9]{8}$/.test(this.form.mobile); },
            async submit() {
                if (!this.isMobileValid) return;
                this.loading = true; this.errors = {};
                try {
                    let response = await fetch('{{ route('kitchen.register') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(this.form)
                    });
                    let result = await response.json();
                    if (response.ok) { window.location.href = result.redirect; }
                    else { this.errors = result.errors; }
                } catch (e) { this.errors = { general: ['Registration failed.'] }; }
                this.loading = false;
            }
         }">
        
        <div class="w-full max-w-4xl bg-middo-cream md:bg-white shadow-xl rounded-[32px] overflow-hidden">
            
            <div class="p-4 md:px-12 md:pt-8 text-right">
                <a href="{{ route('register') }}" class="text-sm font-bold text-middo-orange hover:underline flex items-center justify-end">
                    <span class="mr-2">&larr;</span> Back to Corporate Sign up
                </a>
            </div>

            <div class="relative w-full p-8 md:p-12 overflow-hidden border-b border-gray-200">
                <div class="absolute inset-0 z-0">
                    <img src="{{ asset('img/public/register.jpg') }}" 
                         class="w-full h-full object-cover opacity-20" alt="Middo Kitchen">
                </div>
                <div class="relative z-10">
                    <p class="text-middo-orange font-bold uppercase tracking-wider text-sm mb-2">Kitchen Partner Sign-Up</p>
                    <h1 class="text-3xl md:text-5xl font-extrabold text-middo-dark leading-tight max-w-2xl">
                        Become a Certified Middo Kitchen.
                    </h1>
                </div>
            </div>

            <div class="p-8 md:p-12">
                <form @submit.prevent="submit">
                    <template x-if="errors.general"><div class="bg-red-100 text-red-600 p-4 rounded-xl mb-4" x-text="errors.general[0]"></div></template>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <input x-model="form.first_name" type="text" placeholder="First Name" class="w-full p-4 rounded-xl border border-gray-300">
                            <template x-if="errors.first_name"><p class="text-red-500 text-xs mt-1" x-text="errors.first_name[0]"></p></template>
                        </div>
                        <div>
                            <input x-model="form.last_name" type="text" placeholder="Last Name" class="w-full p-4 rounded-xl border border-gray-300">
                            <template x-if="errors.last_name"><p class="text-red-500 text-xs mt-1" x-text="errors.last_name[0]"></p></template>
                        </div>
                    </div>

                    <div class="mb-4">
                        <input x-model="form.mobile" type="text" placeholder="Phone no. 01XXXXXXXXX" 
                            class="w-full p-4 rounded-xl border border-gray-300"
                            :class="!isMobileValid ? 'ring-2 ring-red-500' : ''">
                        <template x-if="!isMobileValid"><p class="text-red-500 text-xs mt-1">Invalid format (01xxxxxxxxx)</p></template>
                        <template x-if="errors.mobile"><p class="text-red-500 text-xs mt-1" x-text="errors.mobile[0]"></p></template>
                    </div>

                    <div class="mb-4">
                        <input x-model="form.password" type="password" placeholder="Password" class="w-full p-4 rounded-xl border border-gray-300">
                        <template x-if="errors.password"><p class="text-red-500 text-xs mt-1" x-text="errors.password[0]"></p></template>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="relative">
                            <div @click="cityOpen = !cityOpen" class="w-full p-4 rounded-xl border border-gray-300 bg-middo-cream md:bg-white cursor-pointer flex justify-between items-center">
                                <span x-text="cityName"></span> <span>▼</span>
                            </div>
                            <template x-if="errors.city_id"><p class="text-red-500 text-xs mt-1" x-text="errors.city_id[0]"></p></template>
                            <div x-show="cityOpen" @click.away="cityOpen = false" class="absolute z-50 w-full mt-2 bg-middo-cream border rounded-xl shadow-xl max-h-60 overflow-y-auto">
                                @foreach(\App\Models\City::all() as $city)
                                    <div class="p-4 hover:bg-white cursor-pointer border-b" @click="form.city_id = {{ $city->id }}; cityName = '{{ $city->name }}'; cityOpen = false; fetch(`/api/areas/{{ $city->id }}`).then(r => r.json()).then(d => areas = d)">{{ $city->name }}</div>
                                @endforeach
                            </div>
                        </div>
                        <div class="relative">
                            <div @click="areaOpen = !areaOpen" class="w-full p-4 rounded-xl border border-gray-300 bg-middo-cream md:bg-white cursor-pointer flex justify-between items-center">
                                <span x-text="areaName"></span> <span>▼</span>
                            </div>
                            <template x-if="errors.area_id"><p class="text-red-500 text-xs mt-1" x-text="errors.area_id[0]"></p></template>
                            <div x-show="areaOpen" @click.away="areaOpen = false" class="absolute z-50 w-full mt-2 bg-middo-cream border rounded-xl shadow-xl max-h-60 overflow-y-auto">
                                <template x-for="area in areas" :key="area.id">
                                    <div class="p-4 hover:bg-white cursor-pointer border-b" @click="form.area_id = area.id; areaName = area.name; areaOpen = false" x-text="area.name"></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <textarea x-model="form.address" placeholder="Kitchen Address" class="w-full p-4 rounded-xl border border-gray-300"></textarea>
                        <template x-if="errors.address"><p class="text-red-500 text-xs mt-1" x-text="errors.address[0]"></p></template>
                    </div>

                    <button type="submit" 
                            :disabled="loading" 
                            class="w-full bg-middo-orange text-white p-4 rounded-xl font-bold hover:opacity-90 transition relative overflow-hidden h-[56px]">
                        
                        <div x-show="!loading" 
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="transform translate-y-full opacity-0"
                            x-transition:enter-end="transform translate-y-0 opacity-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-end="transform -translate-y-full opacity-0"
                            class="absolute inset-0 flex items-center justify-center">
                            Register Kitchen
                        </div>

                        <div x-show="loading" 
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="transform translate-y-full opacity-0"
                            x-transition:enter-end="transform translate-y-0 opacity-100"
                            class="absolute inset-0 flex items-center justify-center">
                            Processing...
                        </div>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.public.app>