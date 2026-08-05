<?php

namespace App\Livewire\Marketing;

use App\Models\Company;
use App\Models\CompanyAppointment;
use App\Support\GroundMarketingSignup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CompanyShow extends Component
{
    public Company $company;

    public string $tab = 'signup';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public ?string $debugOtp = null;

    // Appointment form
    public string $appointmentAt = '';

    public string $appointmentHrName = '';

    public string $appointmentHrMobile = '';

    public string $appointmentNotes = '';

    // Field signup
    public string $signupFirstName = '';

    public string $signupLastName = '';

    public string $signupMobile = '';

    public string $signupOtp = '';

    public string $signupPassword = '';

    public bool $otpSent = false;

    public function mount(Company $company): void
    {
        abort_unless(Auth::user()?->role?->name === 'ground_marketing', 403);
        abort_unless((int) $company->created_by === (int) Auth::id(), 403);
        $this->company = $company;
        $this->appointmentHrName = (string) ($company->hr_name ?? '');
        $this->appointmentHrMobile = (string) ($company->hr_mobile ?? '');
        $this->appointmentAt = now('Asia/Dhaka')->addDay()->format('Y-m-d\\TH:i');
    }

    public function scheduleAppointment(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'appointmentAt' => 'required|date',
            'appointmentHrName' => 'nullable|string|max:120',
            'appointmentHrMobile' => ['nullable', 'regex:/^01[3-9]\d{8}$/'],
            'appointmentNotes' => 'nullable|string|max:2000',
        ]);

        CompanyAppointment::query()->create([
            'company_id' => $this->company->id,
            'scheduled_at' => $this->appointmentAt,
            'hr_name' => $this->appointmentHrName ?: $this->company->hr_name,
            'hr_mobile' => $this->appointmentHrMobile ?: $this->company->hr_mobile,
            'status' => CompanyAppointment::STATUS_SCHEDULED,
            'notes' => $this->appointmentNotes ?: null,
            'created_by' => Auth::id(),
        ]);

        if ($this->company->status === Company::STATUS_LEAD) {
            $this->company->update(['status' => Company::STATUS_APPOINTMENT_SET]);
        }

        $this->appointmentNotes = '';
        $this->statusMessage = 'Appointment scheduled.';
        $this->company->refresh();
        $this->tab = 'appointments';
    }

    public function markAppointmentDone(int $id): void
    {
        $appointment = CompanyAppointment::query()
            ->where('company_id', $this->company->id)
            ->whereKey($id)
            ->firstOrFail();

        $appointment->update([
            'status' => CompanyAppointment::STATUS_DONE,
            'outcome' => $appointment->outcome ?: 'Visited',
        ]);

        if (in_array($this->company->status, [Company::STATUS_LEAD, Company::STATUS_APPOINTMENT_SET], true)) {
            $this->company->update(['status' => Company::STATUS_VISITED]);
        }

        $this->statusMessage = 'Appointment marked done.';
        $this->company->refresh();
    }

    public function sendSignupOtp(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $this->debugOtp = null;

        $this->validate([
            'signupFirstName' => 'required|string|min:2|max:100',
            'signupLastName' => 'required|string|min:1|max:100',
            'signupMobile' => ['required', 'regex:/^01[3-9]\d{8}$/'],
        ]);

        $result = GroundMarketingSignup::sendOtp($this->signupMobile);
        if (! ($result['ok'] ?? false)) {
            $this->errorMessage = $result['message'] ?? 'Could not send OTP.';

            return;
        }

        $this->otpSent = true;
        $this->debugOtp = $result['debug_otp'] ?? null;
        $this->statusMessage = $this->debugOtp
            ? 'OTP sent. Debug code: '.$this->debugOtp
            : 'OTP sent to '.$this->signupMobile.'. Ask the employee for the 4-digit code.';
    }

    public function completeSignup(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $this->validate([
                'signupFirstName' => 'required|string|min:2|max:100',
                'signupLastName' => 'required|string|min:1|max:100',
                'signupMobile' => ['required', 'regex:/^01[3-9]\d{8}$/'],
                'signupOtp' => 'required|string|size:4',
                'signupPassword' => 'required|string|min:8',
            ], [
                'signupOtp.size' => 'Enter the 4-digit verification code.',
            ]);

            if (! $this->otpSent) {
                throw new \RuntimeException('Send the OTP first, then confirm with the code.');
            }

            $user = GroundMarketingSignup::complete($this->company, [
                'first_name' => $this->signupFirstName,
                'last_name' => $this->signupLastName,
                'mobile' => $this->signupMobile,
                'otp' => $this->signupOtp,
                'password' => $this->signupPassword,
            ], (int) Auth::id());

            $name = $user->name;
            $this->resetSignupForm();
            $this->company->refresh();
            $this->statusMessage = "Signed up {$name} ({$user->mobile}). Address copied from {$this->company->name}.";
            $this->tab = 'employees';
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not complete signup.';
        }
    }

    protected function resetSignupForm(): void
    {
        $this->signupFirstName = '';
        $this->signupLastName = '';
        $this->signupMobile = '';
        $this->signupOtp = '';
        $this->signupPassword = '';
        $this->otpSent = false;
        $this->debugOtp = null;
    }

    public function render()
    {
        $this->company->load(['city', 'area', 'createdByUser']);

        $appointments = $this->company->appointments()
            ->latest('scheduled_at')
            ->limit(20)
            ->get();

        $employees = $this->company->employees()
            ->latest('id')
            ->limit(50)
            ->get();

        return view('livewire.marketing.company-show', [
            'appointments' => $appointments,
            'employees' => $employees,
        ])->layout('layouts.private.app', ['title' => $this->company->name]);
    }
}
