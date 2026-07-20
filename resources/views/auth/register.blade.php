<x-layouts.public.app>
    <div class="min-h-screen bg-middo-cream md:bg-white py-8 md:px-4 flex flex-col justify-start items-center"
         x-data="{ 
            form: { first_name: '', last_name: '', mobile: '', otp: '', password: '', company_name: '', address: '', city_id: '', area_id: '' },
            errors: {},
            loading: false,
            otpSent: false,
            otpModalOpen: false,
            debugOtp: null,
            cityName: 'Select City', areaName: 'Select Area', cityOpen: false, areaOpen: false, areas: [],
            get isMobileValid() { return this.form.mobile.length === 0 || /^01[3-9][0-9]{8}$/.test(this.form.mobile); },
            focusOtpInput() {
                this.$nextTick(() => {
                    this.$refs.otpInput?.focus();
                });
            },
            openOtpModal() {
                this.otpModalOpen = true;
                this.focusOtpInput();
            },
            closeOtpModal() {
                this.otpModalOpen = false;
            },
            async sendOtp({ openModal = true } = {}) {
                if (!this.isMobileValid || this.form.mobile.length !== 11) {
                    this.errors = { mobile: ['Provide a valid 11-digit mobile number (e.g. 01710123456).'] };
                    return false;
                }
                this.loading = true; this.errors = {};
                try {
                    let response = await fetch('{{ route('register.send-otp') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ mobile: this.form.mobile })
                    });
                    let result = await response.json();
                    if (response.ok) {
                        this.otpSent = true;
                        this.debugOtp = result.debug_otp || null;
                        this.form.otp = '';
                        if (openModal) this.openOtpModal();
                        this.loading = false;
                        return true;
                    }
                    this.errors = result.errors || { general: [result.message || 'Failed to send OTP.'] };
                } catch (e) {
                    this.errors = { general: ['Failed to send OTP. Please try again.'] };
                }
                this.loading = false;
                return false;
            },
            async submit() {
                if (!this.isMobileValid) return;
                if (!this.otpSent) {
                    await this.sendOtp({ openModal: true });
                    this.loading = false;
                    return;
                }
                if (!this.otpModalOpen) {
                    this.openOtpModal();
                    return;
                }
                this.loading = true; this.errors = {};
                try {
                    let response = await fetch('{{ route('register') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify(this.form)
                    });
                    let result = await response.json();
                    if (response.ok) {
                        window.location.href = result.redirect;
                        return;
                    }
                    this.errors = result.errors || {};
                    // Keep the modal open only when the OTP itself failed; otherwise show field errors on the form.
                    if (!this.errors.otp) {
                        this.closeOtpModal();
                    } else {
                        this.focusOtpInput();
                    }
                } catch (e) {
                    this.errors = { general: ['Registration failed. Please try again.'] };
                    this.closeOtpModal();
                }
                this.loading = false;
            }
         }"
         @keydown.escape.window="if (otpModalOpen && !loading) closeOtpModal()">
        
        <div class="w-full max-w-4xl bg-middo-cream md:bg-white md:shadow-xl md:rounded-[32px] mb-12">
            
            <div class="p-4 md:px-12 md:pt-8 text-right">
                <a href="/kitchen-signup" class="text-sm font-bold text-middo-orange hover:underline flex items-center justify-end">
                    Go to Kitchen Sign up <span class="ml-2">&rarr;</span>
                </a>
            </div>

            <div class="relative w-full p-8 md:p-12 border-b border-gray-200">
                <div class="absolute inset-0 z-0 opacity-20">
                    <img src="{{ asset('img/public/register.jpg') }}" class="w-full h-full object-cover" alt="Middo Kitchen">
                </div>
                <div class="relative z-10">
                    <p class="text-middo-orange font-bold uppercase tracking-wider text-sm mb-2">Corporate Sign-Up</p>
                    <h1 class="text-3xl md:text-5xl font-extrabold text-middo-dark leading-tight max-w-2xl">
                        Build your perfect lunch program. Join Middo.
                    </h1>
                </div>
            </div>

            <div class="p-8 md:p-12">
                <form @submit.prevent="submit">
                    <template x-if="errors.general"><div class="bg-red-100 text-red-600 p-4 rounded-xl mb-4" x-text="errors.general[0]"></div></template>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <input x-model="form.first_name" type="text" placeholder="First Name" class="w-full p-4 rounded-xl border border-gray-300" :disabled="loading">
                            <template x-if="errors.first_name"><p class="text-red-500 text-xs mt-1" x-text="errors.first_name[0]"></p></template>
                        </div>
                        <div>
                            <input x-model="form.last_name" type="text" placeholder="Last Name" class="w-full p-4 rounded-xl border border-gray-300" :disabled="loading">
                            <template x-if="errors.last_name"><p class="text-red-500 text-xs mt-1" x-text="errors.last_name[0]"></p></template>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex gap-2">
                            <input x-model="form.mobile" type="text" placeholder="Phone no. 01XXXXXXXXX" 
                                class="w-full p-4 rounded-xl border border-gray-300"
                                :class="!isMobileValid ? 'ring-2 ring-red-500' : ''"
                                :disabled="loading || otpSent"
                                maxlength="11">
                            <button type="button" @click="sendOtp({ openModal: true })" :disabled="loading || !isMobileValid || form.mobile.length !== 11"
                                class="shrink-0 px-4 rounded-xl border border-middo-orange text-middo-orange font-bold text-sm hover:bg-middo-orange hover:text-white transition disabled:opacity-50">
                                <span x-text="otpSent ? 'Resend OTP' : 'Send OTP'"></span>
                            </button>
                        </div>
                        <template x-if="!isMobileValid"><p class="text-red-500 text-xs mt-1">Invalid format (01xxxxxxxxx)</p></template>
                        <template x-if="errors.mobile"><p class="text-red-500 text-xs mt-1" x-text="errors.mobile[0]"></p></template>
                        <p x-show="otpSent" x-cloak class="text-gray-500 text-xs mt-2">
                            Code sent.
                            <button type="button" @click="openOtpModal" class="text-middo-orange font-bold hover:underline">Enter OTP</button>
                        </p>
                    </div>

                    <div class="mb-4">
                        <input x-model="form.password" type="password" placeholder="Password" class="w-full p-4 rounded-xl border border-gray-300" :disabled="loading">
                        <template x-if="errors.password"><p class="text-red-500 text-xs mt-1" x-text="errors.password[0]"></p></template>
                    </div>

                    <div class="border-t pt-6 mt-6">
                        <h2 class="text-lg font-bold text-middo-dark mb-4">Corporate Details</h2>
                        <input x-model="form.company_name" type="text" placeholder="Company Name" class="w-full p-4 rounded-xl border border-gray-300 mb-4" :disabled="loading">
                        <template x-if="errors.company_name"><p class="text-red-500 text-xs mt-1 mb-4" x-text="errors.company_name[0]"></p></template>
                        
                        <textarea x-model="form.address" placeholder="Company Address" class="w-full p-4 rounded-xl border border-gray-300 mb-6" :disabled="loading"></textarea>
                        <template x-if="errors.address"><p class="text-red-500 text-xs mt-1" x-text="errors.address[0]"></p></template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="relative">
                                <div @click="cityOpen = !cityOpen" class="w-full p-4 rounded-xl border border-gray-300 bg-middo-cream md:bg-white cursor-pointer flex justify-between">
                                    <span x-text="cityName"></span> <span>▼</span>
                                </div>
                                <template x-if="errors.city_id"><p class="text-red-500 text-xs mt-1" x-text="errors.city_id[0]"></p></template>
                                <div x-show="cityOpen" @click.away="cityOpen = false" class="absolute z-50 w-full mt-2 bg-middo-cream border rounded-xl shadow-xl max-h-60 overflow-y-auto">
                                    @foreach(\App\Models\City::all() as $city)
                                        <div class="p-4 cursor-pointer hover:bg-white border-b" 
                                            @click="form.city_id = {{ $city->id }}; cityName = '{{ $city->name }}'; cityOpen = false; fetch(`/api/areas/{{ $city->id }}`).then(r => r.json()).then(d => areas = d)">{{ $city->name }}</div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="relative">
                                <div @click="areaOpen = !areaOpen" class="w-full p-4 rounded-xl border border-gray-300 bg-middo-cream md:bg-white cursor-pointer flex justify-between">
                                    <span x-text="areaName"></span> <span>▼</span>
                                </div>
                                <template x-if="errors.area_id"><p class="text-red-500 text-xs mt-1" x-text="errors.area_id[0]"></p></template>
                                <div x-show="areaOpen" @click.away="areaOpen = false" class="absolute z-50 w-full mt-2 bg-middo-cream border rounded-xl shadow-xl max-h-60 overflow-y-auto">
                                    <template x-for="area in areas" :key="area.id">
                                        <div class="p-4 cursor-pointer hover:bg-white border-b" @click="form.area_id = area.id; areaName = area.name; areaOpen = false" x-text="area.name"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" :disabled="loading" class="w-full bg-middo-orange text-white p-4 rounded-xl font-bold hover:opacity-90 transition">
                        <span x-show="!loading" x-text="otpSent ? 'Enter OTP & Sign Up' : 'Send OTP & Continue'"></span>
                        <span x-show="loading">Processing...</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- OTP verification modal: keeps the long signup form intact on large screens --}}
        <div x-show="otpModalOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="signup-otp-title">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="!loading && closeOtpModal()"></div>
            <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl p-6 md:p-8"
                 @click.stop
                 x-show="otpModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-2 scale-95">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 id="signup-otp-title" class="text-xl font-extrabold text-middo-dark">Verify your mobile</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Enter the 4-digit code sent to
                            <span class="font-bold text-middo-dark" x-text="form.mobile"></span>.
                        </p>
                    </div>
                    <button type="button" @click="closeOtpModal" :disabled="loading"
                            class="text-gray-400 hover:text-middo-dark text-2xl leading-none disabled:opacity-50" aria-label="Close">
                        &times;
                    </button>
                </div>

                <template x-if="debugOtp">
                    <p class="text-middo-orange text-xs font-bold mb-3" x-text="'Debug OTP: ' + debugOtp"></p>
                </template>

                <label for="signup-otp-input" class="sr-only">SMS OTP</label>
                <input x-ref="otpInput" id="signup-otp-input" x-model="form.otp" type="text" inputmode="numeric" maxlength="4"
                       placeholder="4-digit SMS OTP"
                       class="w-full p-4 rounded-xl border border-gray-300 tracking-[0.4em] text-center text-xl font-bold"
                       :disabled="loading"
                       @keydown.enter.prevent="submit()">
                <template x-if="errors.otp"><p class="text-red-500 text-xs mt-2" x-text="errors.otp[0]"></p></template>
                <p class="text-gray-500 text-xs mt-2">Valid for 5 minutes.</p>

                <div class="mt-6 space-y-3">
                    <button type="button" @click="submit" :disabled="loading || form.otp.length !== 4"
                            class="w-full bg-middo-orange text-white p-4 rounded-xl font-bold hover:opacity-90 transition disabled:opacity-50">
                        <span x-show="!loading">Verify & Sign Up</span>
                        <span x-show="loading">Processing...</span>
                    </button>
                    <button type="button" @click="sendOtp({ openModal: true })" :disabled="loading"
                            class="w-full rounded-xl border border-middo-orange text-middo-orange font-bold text-sm py-3 hover:bg-middo-orange hover:text-white transition disabled:opacity-50">
                        Resend OTP
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.public.app>
