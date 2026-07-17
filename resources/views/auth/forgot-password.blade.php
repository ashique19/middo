<x-layouts.public.app>
    <div class="min-h-screen flex flex-col md:flex-row bg-middo-green">

        <div class="hidden md:block md:w-1/2 lg:w-3/5 relative">
            <img src="{{ asset('img/public/login.jpg') }}"
                 class="w-full h-full object-cover opacity-90" alt="Middo Kitchen">
        </div>

        <div class="w-full md:w-1/2 lg:w-2/5 flex items-center justify-center p-8">
            <div class="w-full max-w-sm" x-data="{
                step: '{{ old('otp') || session('reset_step') === 'reset' ? 'reset' : 'mobile' }}',
                mobile: '{{ old('mobile', session('reset_mobile', '')) }}'
            }">
                <div class="md:hidden relative mb-8">
                    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-middo-green z-10"></div>
                    <img src="{{ asset('img/public/login.jpg') }}" class="w-full rounded-lg" alt="Middo Kitchen">
                </div>

                <h1 class="text-3xl font-extrabold mb-2 text-middo-orange">Reset Password</h1>
                <p class="text-white/90 mb-8 font-medium">
                    <span x-show="step === 'mobile'">Enter your registered mobile number to receive a reset code.</span>
                    <span x-show="step === 'reset'" x-cloak>Enter the SMS code and choose a new password.</span>
                </p>

                @if (session('status'))
                    <div class="mb-4 rounded-xl bg-emerald-100 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-semibold">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('debug_otp'))
                    <div class="mb-4 rounded-xl bg-amber-100 border border-amber-200 text-amber-900 px-4 py-3 text-sm font-semibold">
                        Debug OTP: {{ session('debug_otp') }}
                    </div>
                @endif

                {{-- Step 1: request OTP --}}
                <form action="{{ route('password.email') }}" method="POST" x-show="step === 'mobile'">
                    @csrf

                    <div class="mb-6 relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-middo-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        </span>
                        <input type="text" name="mobile" x-model="mobile" placeholder="Phone Number" value="{{ old('mobile') }}"
                            class="w-full pl-12 p-4 bg-middo-cream text-middo-dark border-0 rounded-xl focus:ring-2 focus:ring-middo-orange outline-none placeholder:text-gray-500 @error('mobile') ring-2 ring-red-500 @enderror">

                        @error('mobile')
                            <p class="text-red-400 text-xs mt-1 ml-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full bg-middo-orange text-white p-4 rounded-xl font-bold hover:opacity-90 transition shadow-lg">
                        Send Reset Code
                    </button>
                </form>

                {{-- Step 2: verify OTP + new password --}}
                <form action="{{ route('password.update') }}" method="POST" x-show="step === 'reset'" x-cloak>
                    @csrf
                    <input type="hidden" name="mobile" :value="mobile">

                    <div class="mb-4 relative">
                        <input type="text" name="otp" inputmode="numeric" maxlength="4" placeholder="4-digit SMS code" value="{{ old('otp') }}"
                            class="w-full p-4 bg-middo-cream text-middo-dark border-0 rounded-xl focus:ring-2 focus:ring-middo-orange outline-none placeholder:text-gray-500 @error('otp') ring-2 ring-red-500 @enderror">
                        @error('otp')
                            <p class="text-red-400 text-xs mt-1 ml-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4 relative">
                        <input type="password" name="password" placeholder="New password (min 8 characters)"
                            class="w-full p-4 bg-middo-cream text-middo-dark border-0 rounded-xl focus:ring-2 focus:ring-middo-orange outline-none placeholder:text-gray-500 @error('password') ring-2 ring-red-500 @enderror">
                        @error('password')
                            <p class="text-red-400 text-xs mt-1 ml-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6 relative">
                        <input type="password" name="password_confirmation" placeholder="Confirm new password"
                            class="w-full p-4 bg-middo-cream text-middo-dark border-0 rounded-xl focus:ring-2 focus:ring-middo-orange outline-none placeholder:text-gray-500">
                    </div>

                    <button type="submit" class="w-full bg-middo-orange text-white p-4 rounded-xl font-bold hover:opacity-90 transition shadow-lg">
                        Update Password
                    </button>

                    <button type="button" @click="step = 'mobile'" class="w-full mt-3 text-sm text-white/80 font-medium hover:underline">
                        Use a different mobile number
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-white font-medium hover:underline">
                        Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.public.app>
