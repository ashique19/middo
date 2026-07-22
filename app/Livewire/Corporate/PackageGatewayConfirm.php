<?php

namespace App\Livewire\Corporate;

use App\Contracts\PaymentGateway;
use App\Models\MealPackage;
use App\Support\OrderConfirmationOtp;
use App\Support\PackageGatewayCheckout;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PackageGatewayConfirm extends Component
{
    public string $token = '';

    public bool $paid = false;

    public int $amount = 0;

    public string $mobile = '';

    public string $customerName = '';

    public string $packageName = '';

    public string $otpInput = '';

    public ?string $debugOtp = null;

    public string $errorMessage = '';

    public string $statusMessage = '';

    public ?string $paymentUrl = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->bootFromSession(sendOtpIfPaid: true);
    }

    public function resendOtp(): void
    {
        $this->errorMessage = '';
        $this->bootFromSession(sendOtpIfPaid: false);

        if (! $this->paid) {
            $this->errorMessage = 'Complete online payment before requesting an OTP.';
            $this->scrollFeedbackIntoView();

            return;
        }

        $otpResult = OrderConfirmationOtp::send($this->mobile);
        if (! ($otpResult['ok'] ?? false)) {
            $this->errorMessage = $otpResult['message'] ?? 'Could not send OTP. Try again.';
            $this->scrollFeedbackIntoView();

            return;
        }

        $this->otpInput = '';
        $this->debugOtp = isset($otpResult['debug_otp']) ? (string) $otpResult['debug_otp'] : null;
        $this->statusMessage = $this->debugOtp
            ? 'OTP resent. Debug code: '.$this->debugOtp
            : 'OTP resent to '.$this->mobile.'.';
    }

    public function createPackage(): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->resetErrorBag();
        $this->otpInput = preg_replace('/\D+/', '', trim((string) $this->otpInput)) ?? '';

        $this->bootFromSession(sendOtpIfPaid: false);

        if (! $this->paid) {
            $this->errorMessage = 'Complete online payment before confirming.';
            $this->scrollFeedbackIntoView();

            return;
        }

        try {
            $this->validate([
                'otpInput' => 'required|string|size:4',
            ]);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->validator->errors()->all())->implode(' ');
            $this->setErrorBag($e->validator->errors());
            $this->scrollFeedbackIntoView();

            return;
        }

        if (! OrderConfirmationOtp::verify($this->mobile, $this->otpInput)) {
            $this->errorMessage = 'Invalid or expired confirmation code. Request a new OTP and try again.';
            $this->addError('otpInput', 'Invalid or expired confirmation code.');
            $this->scrollFeedbackIntoView();

            return;
        }

        $draft = PackageGatewayCheckout::findDraft($this->token);
        $payload = app(PaymentGateway::class)->find($this->token);
        if (! is_array($draft) || ! is_array($payload)) {
            $this->errorMessage = 'Payment session expired. Start package checkout again.';
            $this->scrollFeedbackIntoView();

            return;
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        try {
            $result = app(PackageSubscriptionService::class)->subscribe(
                Auth::user(),
                MealPackage::findOrFail((int) ($metadata['meal_package_id'] ?? 0)),
                (int) ($metadata['quantity'] ?? 1),
                $metadata['omitted_weekdays'] ?? [5, 6],
                $metadata['selections'] ?? [],
                (string) ($metadata['target_month'] ?? ''),
                (string) ($draft['customer_name'] ?? ''),
                (string) ($draft['mobile'] ?? ''),
                (string) ($draft['address_line1'] ?? ''),
                (int) ($draft['city_id'] ?? 0),
                (int) ($draft['area_id'] ?? 0),
                (string) ($draft['delivery_window'] ?? '12:00 PM'),
                'gateway',
                $this->token
            );
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = $e->getMessage() ?: 'Could not create the package. Please try again.';
            $this->scrollFeedbackIntoView();

            return;
        }

        PackageGatewayCheckout::forgetDraft($this->token);

        $subscriptionId = $result['subscription']->id;
        $days = (int) $result['subscription']->billable_days;

        session()->flash(
            'message',
            'Package prepaid for '.$days.' days. Middo operations will assign exact delivery dates next.'
        );

        $this->redirect(route('corporates.packages.show', $subscriptionId));
    }

    protected function scrollFeedbackIntoView(): void
    {
        $this->js(<<<'JS'
            queueMicrotask(() => {
                document.getElementById('pkg-confirm-feedback')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        JS);
    }

    protected function bootFromSession(bool $sendOtpIfPaid): void
    {
        $gateway = app(PaymentGateway::class);
        $payload = $gateway->find($this->token);
        $draft = PackageGatewayCheckout::findDraft($this->token);

        if (! is_array($payload) || ! is_array($draft)) {
            $this->errorMessage = 'Payment session expired or invalid. Start package checkout again.';
            $this->paid = false;

            return;
        }

        if ((int) ($payload['user_id'] ?? 0) !== (int) Auth::id()) {
            abort(403);
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        if (($metadata['purpose'] ?? null) !== PackageGatewayCheckout::PURPOSE) {
            $this->errorMessage = 'This payment session is not a package checkout.';
            $this->paid = false;

            return;
        }

        $this->paid = (bool) ($payload['paid'] ?? false);
        $this->amount = (int) ($payload['amount'] ?? 0);
        $this->mobile = (string) ($draft['mobile'] ?? '');
        $this->customerName = (string) ($draft['customer_name'] ?? '');
        $this->paymentUrl = $gateway->paymentUrl($this->token);

        $package = MealPackage::query()->find((int) ($metadata['meal_package_id'] ?? 0));
        $this->packageName = $package?->name ?? 'Monthly package';

        if ($this->paid && $sendOtpIfPaid && $this->mobile !== '') {
            $otpResult = OrderConfirmationOtp::send($this->mobile);
            if (! ($otpResult['ok'] ?? false)) {
                $this->errorMessage = $otpResult['message'] ?? 'Could not send OTP. Try again.';

                return;
            }

            $this->debugOtp = isset($otpResult['debug_otp']) ? (string) $otpResult['debug_otp'] : null;
            $this->statusMessage = $this->debugOtp
                ? 'Payment received. Enter OTP to create your package. Debug code: '.$this->debugOtp
                : 'Payment received. Enter the OTP sent to '.$this->mobile.' to create your package.';
        } elseif (! $this->paid) {
            $this->statusMessage = 'Finish payment first, then we will ask for OTP to create your package.';
        }
    }

    public function render()
    {
        return view('livewire.corporate.package-gateway-confirm')
            ->layout('layouts.public.app');
    }
}
