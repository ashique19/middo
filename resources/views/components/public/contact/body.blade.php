<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-12">
        
        <div class="space-y-8">
            <h2 class="text-3xl font-bold text-[#2D2D2D]">Corporate & Partners</h2>
            
            <div class="p-6 border border-gray-200 rounded-3xl bg-gray-50">
                <h3 class="text-sm font-bold text-[#2A3B31] uppercase mb-4">Office Lunch</h3>
                <p class="font-bold text-lg mb-2">Individual Plan Support (Sat-Thu, 9am-6pm)</p>
                <a href="tel:01710123456" class="text-middo-orange block mb-1">📞 01710123456</a>
                <a href="mailto:b2c_support@middo.com.bd" class="text-middo-orange block mb-1">✉ b2c_support@middo.com.bd</a>
                <a href="{{ route('faq') }}" class="text-middo-orange text-sm underline">↗ Check FAQs</a>
            </div>

            <div class="p-6 border border-gray-200 rounded-3xl bg-gray-50">
                <h3 class="font-bold text-lg mb-2">Kitchen Partnership Opportunities</h3>
                <p class="text-gray-600 mb-4">General Partnership</p>
                <a href="mailto:kitchen_onboarding@middo.com.bd" class="text-middo-orange block mb-1">✉ kitchen_onboarding@middo.com.bd</a>
                <a href="mailto:b2b_sales@middo.com.bd" class="text-middo-orange block">✉ b2b_sales@middo.com.bd</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-6 border border-gray-200 rounded-3xl bg-gray-50">
                    <h3 class="font-bold mb-1">Headquarters</h3>
                    <p class="text-sm text-gray-600 mb-3">Middo Headquarters Office</p>
                    <button class="bg-black text-white px-4 py-1 rounded-full text-xs">Map</button>
                </div>
                <div class="p-6 border border-gray-200 rounded-3xl bg-gray-50">
                    <h3 class="font-bold mb-1">Careers</h3>
                    <p class="text-sm text-gray-600 mb-3">Join Our Team</p>
                    <a href="mailto:career@middo.com.bd" class="text-middo-orange text-sm font-bold">✉ career@middo.com.bd</a>
                </div>
            </div>
        </div>

        <div class="p-8 border border-gray-200 rounded-3xl shadow-sm bg-white">
            <h2 class="text-3xl font-bold mb-6">Reach Out Directly</h2>

            @if (session('success'))
                <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('contact.submit') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <input type="text" name="name" placeholder="Name" value="{{ old('name') }}" class="w-full p-3 border rounded-xl @error('name') border-red-500 @else border-gray-300 @enderror" required>
                        @error('name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <input type="tel" 
                            name="phone" 
                            placeholder="Phone Number (e.g., 01710123456)" 
                            pattern="01[3-9][0-9]{8}" 
                            title="Must be a valid 11-digit Bangladeshi number starting with 01"
                            value="{{ old('phone') }}" 
                            oninput="validatePhone(this)"
                            class="w-full p-3 border rounded-xl transition-colors duration-200 @error('phone') border-red-500 @else border-gray-300 @enderror" 
                            required>
                        
                        <!-- Real-time feedback element -->
                        <p id="phone-error" class="text-red-500 text-xs mt-1 hidden">
                            Invalid format. Use 01XXXXXXXXX (11 digits).
                        </p>

                        @error('phone')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div>
                    <input type="email" 
                        name="email" 
                        placeholder="Email Address (Optional)" 
                        value="{{ old('email') }}" 
                        oninput="validateEmail(this)"
                        class="w-full p-3 border rounded-xl transition-colors duration-200 @error('email') border-red-500 @else border-gray-300 @enderror">
                    
                    <!-- Real-time feedback element -->
                    <p id="email-error" class="text-red-500 text-xs mt-1 hidden">
                        Please enter a valid email address.
                    </p>

                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                
                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                <input type="text" name="company" placeholder="Company/Kitchen Name" value="{{ old('company') }}" class="w-full p-3 border rounded-xl @error('company') border-red-500 @else border-gray-300 @enderror">
                @error('company') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                <textarea name="message" placeholder="Message" class="w-full p-3 border rounded-xl h-32 @error('message') border-red-500 @else border-gray-300 @enderror" required>{{ old('message') }}</textarea>
                @error('message') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                <button type="submit" class="w-full bg-[#2D2D2D] text-white py-4 rounded-xl font-bold hover:bg-black transition">
                    Send Message
                </button>
            </form>
        </div>
    </div>
</section>



<script>
function validatePhone(input) {
    const errorElement = document.getElementById('phone-error');
    if (input.value.length === 0) {
        input.classList.remove('border-red-500', 'border-green-500');
        errorElement.classList.add('hidden');
    } else if (input.checkValidity()) {
        input.classList.remove('border-red-500');
        input.classList.add('border-green-500');
        errorElement.classList.add('hidden');
    } else {
        input.classList.remove('border-green-500');
        input.classList.add('border-red-500');
        errorElement.classList.remove('hidden');
    }
}
</script>

<script>
function validateEmail(input) {
    const errorElement = document.getElementById('email-error');
    const value = input.value;
    
    // Check if empty (optional field)
    if (value.length === 0) {
        input.classList.remove('border-red-500', 'border-green-500');
        input.classList.add('border-gray-300');
        errorElement.classList.add('hidden');
    } 
    // Validate against standard email regex
    else if (input.checkValidity()) {
        input.classList.remove('border-red-500', 'border-gray-300');
        input.classList.add('border-green-500');
        errorElement.classList.add('hidden');
    } 
    // Invalid format
    else {
        input.classList.remove('border-green-500', 'border-gray-300');
        input.classList.add('border-red-500');
        errorElement.classList.remove('hidden');
    }
}
</script>