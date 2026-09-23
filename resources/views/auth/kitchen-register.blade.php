<x-layouts.public.app>
    <div class="min-h-screen bg-middo-cream md:p-8 flex items-center justify-center"
         x-data="{ 
            form: { first_name: '', last_name: '', mobile: '', password: '', address: '', city_id: '', area_id: '', nid_number: '' },
            photos: {
                nidFront: { file: null, preview: null, name: '' },
                nidBack: { file: null, preview: null, name: '' },
                selfie: { file: null, preview: null, name: '' },
            },
            errors: {}, loading: false,
            cityName: 'Select City', areaName: 'Select Area', cityOpen: false, areaOpen: false, areas: [],
            get isMobileValid() { return this.form.mobile.length === 0 || /^01[3-9][0-9]{8}$/.test(this.form.mobile); },
            setPhoto(slot, file) {
                const current = this.photos[slot];
                if (current.preview) URL.revokeObjectURL(current.preview);
                if (!file) {
                    this.photos[slot] = { file: null, preview: null, name: '' };
                    return;
                }
                this.photos[slot] = {
                    file,
                    preview: URL.createObjectURL(file),
                    name: file.name,
                };
            },
            clearPhoto(slot) {
                this.setPhoto(slot, null);
            },
            async submit() {
                if (!this.isMobileValid) return;
                this.loading = true; this.errors = {};
                try {
                    const body = new FormData();
                    Object.entries(this.form).forEach(([key, value]) => body.append(key, value ?? ''));
                    if (this.photos.nidFront.file) body.append('nid_front', this.photos.nidFront.file);
                    if (this.photos.nidBack.file) body.append('nid_back', this.photos.nidBack.file);
                    if (this.photos.selfie.file) body.append('selfie', this.photos.selfie.file);
                    let response = await fetch('{{ route('kitchen.register') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body
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

                    <div class="mb-6 rounded-2xl border border-gray-200 bg-white/70 md:bg-middo-cream/40 p-5 space-y-4">
                        <div>
                            <p class="text-sm font-bold text-middo-dark">Verification photos (optional)</p>
                            <p class="text-xs text-gray-500 mt-1">Add NID front, NID back, and a chef selfie. Photos are compressed. The selfie becomes your kitchen profile photo.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">NID number</label>
                            <input x-model="form.nid_number" type="text" inputmode="numeric" placeholder="10–17 digits" class="w-full p-4 rounded-xl border border-gray-300 bg-white">
                            <template x-if="errors.nid_number"><p class="text-red-500 text-xs mt-1" x-text="errors.nid_number[0]"></p></template>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- NID front --}}
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">NID front</p>
                                    <button type="button" x-show="photos.nidFront.file" @click="clearPhoto('nidFront')"
                                            class="text-[11px] font-bold text-middo-orange hover:underline">Clear</button>
                                </div>
                                <label class="relative block h-40 rounded-2xl border-2 border-dashed overflow-hidden cursor-pointer transition"
                                       :class="photos.nidFront.preview ? 'border-middo-orange/40 bg-white' : 'border-gray-300 bg-white hover:border-middo-orange/60 hover:bg-orange-50/40'">
                                    <input type="file" accept="image/*" class="sr-only"
                                           @change="setPhoto('nidFront', $event.target.files[0] || null); $event.target.value = ''">
                                    <div x-show="photos.nidFront.preview" class="absolute inset-0" x-cloak>
                                        <img :src="photos.nidFront.preview" alt="NID front" class="h-full w-full object-cover">
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-3">
                                            <p class="text-[11px] font-semibold text-white truncate" x-text="photos.nidFront.name"></p>
                                            <p class="text-[10px] text-white/80">Tap to replace</p>
                                        </div>
                                    </div>
                                    <div x-show="!photos.nidFront.preview" class="absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                                        <span class="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-middo-orange/10 text-middo-orange text-lg font-bold">+</span>
                                        <p class="text-sm font-bold text-middo-dark">Add NID front</p>
                                        <p class="text-[11px] text-gray-500 mt-1">Card front photo</p>
                                    </div>
                                </label>
                                <template x-if="errors.nid_front"><p class="text-red-500 text-xs" x-text="errors.nid_front[0]"></p></template>
                            </div>

                            {{-- NID back --}}
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">NID back</p>
                                    <button type="button" x-show="photos.nidBack.file" @click="clearPhoto('nidBack')"
                                            class="text-[11px] font-bold text-middo-orange hover:underline">Clear</button>
                                </div>
                                <label class="relative block h-40 rounded-2xl border-2 border-dashed overflow-hidden cursor-pointer transition"
                                       :class="photos.nidBack.preview ? 'border-middo-orange/40 bg-white' : 'border-gray-300 bg-white hover:border-middo-orange/60 hover:bg-orange-50/40'">
                                    <input type="file" accept="image/*" class="sr-only"
                                           @change="setPhoto('nidBack', $event.target.files[0] || null); $event.target.value = ''">
                                    <div x-show="photos.nidBack.preview" class="absolute inset-0" x-cloak>
                                        <img :src="photos.nidBack.preview" alt="NID back" class="h-full w-full object-cover">
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-3">
                                            <p class="text-[11px] font-semibold text-white truncate" x-text="photos.nidBack.name"></p>
                                            <p class="text-[10px] text-white/80">Tap to replace</p>
                                        </div>
                                    </div>
                                    <div x-show="!photos.nidBack.preview" class="absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                                        <span class="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-middo-orange/10 text-middo-orange text-lg font-bold">+</span>
                                        <p class="text-sm font-bold text-middo-dark">Add NID back</p>
                                        <p class="text-[11px] text-gray-500 mt-1">Card back photo</p>
                                    </div>
                                </label>
                                <template x-if="errors.nid_back"><p class="text-red-500 text-xs" x-text="errors.nid_back[0]"></p></template>
                            </div>

                            {{-- Chef selfie --}}
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Chef selfie</p>
                                    <button type="button" x-show="photos.selfie.file" @click="clearPhoto('selfie')"
                                            class="text-[11px] font-bold text-middo-orange hover:underline">Clear</button>
                                </div>
                                <label class="relative block h-40 rounded-2xl border-2 border-dashed overflow-hidden cursor-pointer transition"
                                       :class="photos.selfie.preview ? 'border-middo-orange/40 bg-white' : 'border-gray-300 bg-white hover:border-middo-orange/60 hover:bg-orange-50/40'">
                                    <input type="file" accept="image/*" class="sr-only"
                                           @change="setPhoto('selfie', $event.target.files[0] || null); $event.target.value = ''">
                                    <div x-show="photos.selfie.preview" class="absolute inset-0" x-cloak>
                                        <img :src="photos.selfie.preview" alt="Chef selfie" class="h-full w-full object-cover">
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-3">
                                            <p class="text-[11px] font-semibold text-white truncate" x-text="photos.selfie.name"></p>
                                            <p class="text-[10px] text-white/80">Tap to replace</p>
                                        </div>
                                    </div>
                                    <div x-show="!photos.selfie.preview" class="absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                                        <span class="mb-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-middo-orange/10 text-middo-orange text-lg font-bold">+</span>
                                        <p class="text-sm font-bold text-middo-dark">Add chef selfie</p>
                                        <p class="text-[11px] text-gray-500 mt-1">Used as profile photo</p>
                                    </div>
                                </label>
                                <template x-if="errors.selfie"><p class="text-red-500 text-xs" x-text="errors.selfie[0]"></p></template>
                            </div>
                        </div>
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