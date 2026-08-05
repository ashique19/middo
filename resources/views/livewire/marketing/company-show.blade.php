<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <a href="{{ route('marketing.companies.index') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Companies</a>
        <div class="flex flex-wrap items-start justify-between gap-3 mt-1">
            <div>
                <h1 class="text-3xl font-bold text-middo-dark">{{ $company->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ \App\Models\Company::statusLabel($company->status) }}
                    · {{ $company->area?->name }}
                    · {{ $company->address }}
                </p>
            </div>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach(['signup' => 'Field signup', 'appointments' => 'Appointments', 'employees' => 'Employees'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === $key, 'bg-white border-gray-200' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($tab === 'signup')
        <form wire:submit="completeSignup" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Sign up employee</h2>
            <p class="text-sm text-gray-500">
                Select this company, enter name + mobile. Address is copied from the company.
                An OTP is sent to the mobile to confirm it is not a fake entry.
            </p>
            <div class="rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">
                Delivery address will be: <span class="font-semibold text-middo-dark">{{ $company->address }}</span>
                ({{ $company->area?->name }}, {{ $company->city?->name }})
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">First name</label>
                    <input type="text" wire:model="signupFirstName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('signupFirstName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Last name</label>
                    <input type="text" wire:model="signupLastName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('signupLastName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Mobile</label>
                <div class="flex flex-wrap gap-2">
                    <input type="text" wire:model="signupMobile" class="flex-1 min-w-[160px] rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="01XXXXXXXXX">
                    <button type="button" wire:click="sendSignupOtp"
                            class="px-4 py-2 rounded-xl border border-middo-orange text-middo-orange text-sm font-bold">
                        Send OTP
                    </button>
                </div>
                @error('signupMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @if($otpSent)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-400 mb-1">OTP code</label>
                        <input type="text" wire:model="signupOtp" maxlength="4" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono" placeholder="4 digits">
                        @error('signupOtp') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Set password</label>
                        <input type="password" wire:model="signupPassword" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Min 8 characters">
                        @error('signupPassword') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Verify &amp; create account</button>
            @endif
        </form>
    @endif

    @if($tab === 'appointments')
        <form wire:submit="scheduleAppointment" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Book HR appointment</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">When</label>
                    <input type="datetime-local" wire:model="appointmentAt" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('appointmentAt') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">HR name</label>
                    <input type="text" wire:model="appointmentHrName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">HR mobile</label>
                    <input type="text" wire:model="appointmentHrMobile" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('appointmentHrMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <textarea wire:model="appointmentNotes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Schedule</button>
        </form>

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b text-sm font-bold text-middo-dark">History</div>
            <ul class="divide-y">
                @forelse($appointments as $appt)
                    <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div>
                            <p class="font-semibold">{{ $appt->scheduled_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                            <p class="text-xs text-gray-500">{{ $appt->hr_name ?: '—' }} · {{ $appt->status }}</p>
                        </div>
                        @if($appt->isScheduled())
                            <button type="button" wire:click="markAppointmentDone({{ $appt->id }})"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Mark done</button>
                        @endif
                    </li>
                @empty
                    <li class="p-8 text-center text-gray-400 italic text-sm">No appointments yet.</li>
                @endforelse
            </ul>
        </div>
    @endif

    @if($tab === 'employees')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm min-w-[480px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Name</th>
                        <th class="p-3 text-left">Mobile</th>
                        <th class="p-3 text-left">Signed up</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($employees as $emp)
                        <tr>
                            <td class="p-3 font-semibold">{{ $emp->name }}</td>
                            <td class="p-3 font-mono text-xs">{{ $emp->mobile }}</td>
                            <td class="p-3 text-gray-500">{{ $emp->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-10 text-center text-gray-400 italic">No employees signed up yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
