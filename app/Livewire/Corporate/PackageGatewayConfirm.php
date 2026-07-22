<?php

namespace App\Livewire\Corporate;

use App\Contracts\PaymentGateway;
use App\Models\MealPackage;
use App\Models\PackageCheckoutIntent;
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

        $intent = PackageGatewayCheckout::findIntent($this->token);
        $otpResult = $intent
            ? PackageGatewayCheckout::pokeOtp($intent, cooldownSeconds: 0)
            : OrderConfirmationOtp::send($this->mobile);

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

        $resolved = PackageGatewayCheckout::resolve($this->token, (int) Auth::id());
        if ($resolved === null) {
            $this->errorMessage = 'Payment session expired. Start package checkout again.';
            $this->scrollFeedbackIntoView();

            return;
        }

        $this->paid = (bool) $resolved['paid'];
        $this->amount = (int) $resolved['amount'];
        $this->mobile = (string) ($resolved['draft']['mobile'] ?? '');
        $this->customerName = (string) ($resolved['draft']['customer_name'] ?? '');

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

        $draft = $resolved['draft'];
        $metadata = $resolved['metadata'];

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
                $this->token,
                filled($draft['coupon_code'] ?? null) ? (string) $draft['coupon_code'] : null
            );
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = $e->getMessage() ?: 'Could not create the package. Please try again.';
            $this->scrollFeedbackIntoView();

            return;
        }

        PackageGatewayCheckout::markCompleted($this->token, $result['subscription']);

        $subscriptionId = $result['subscription']->id;
        $days = (int) $result['subscription']->billable_days;

        session()->flash(
            'message',
            'Package prepaid for '.$days.' days. Middo operations will assign exact delivery dates next.'
        );

        $this->redirect(route('corporates.packages.show', ['subscriptionId' => $subscriptionId]));
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
        $resolved = PackageGatewayCheckout::resolve($this->token, (int) Auth::id());

        if ($resolved === null) {
            $this->errorMessage = 'Payment session expired or invalid. Start package checkout again.';
            $this->paid = false;

            return;
        }

        if (($resolved['metadata']['purpose'] ?? null) !== PackageGatewayCheckout::PURPOSE) {
            $this->errorMessage = 'This payment session is not a package checkout.';
            $this->paid = false;

            return;
        }

        /** @var PackageCheckoutIntent|null $intent */
        $intent = $resolved['intent'];
        if ($intent && $intent->status === PackageCheckoutIntent::STATUS_COMPLETED && $intent->package_subscription_id) {
            $this->redirect(route('corporates.packages.show', ['subscriptionId' => $intent->package_subscription_id]));

            return;
        }

        $this->paid = (bool) $resolved['paid'];
        $this->amount = (int) $resolved['amount'];
        $this->mobile = (string) ($resolved['draft']['mobile'] ?? '');
        $this->customerName = (string) ($resolved['draft']['customer_name'] ?? '');
        $this->paymentUrl = app(PaymentGateway::class)->paymentUrl($this->token);

        $package = MealPackage::query()->find((int) ($resolved['metadata']['meal_package_id'] ?? 0));
        $this->packageName = $package?->name ?? 'Monthly package';

        if ($this->paid && $sendOtpIfPaid && $this->mobile !== '') {
            $otpResult = $intent
                ? PackageGatewayCheckout::pokeOtp($intent, cooldownSeconds: 60)
                : OrderConfirmationOtp::send($this->mobile);

            if (! ($otpResult['ok'] ?? false)) {
                $this->errorMessage = $otpResult['message'] ?? 'Could not send OTP. Try again.';

                return;
            }

            $this->debugOtp = isset($otpResult['debug_otp']) ? (string) $otpResult['debug_otp'] : null;
            if (($otpResult['sent'] ?? true) === false) {
                $this->statusMessage = 'Payment is locked in. Enter the OTP we already sent to '.$this->mobile.', or resend if needed.';
            } else {
                $this->statusMessage = $this->debugOtp
                    ? 'Payment received. Enter OTP to create your package. Debug code: '.$this->debugOtp
                    : 'Payment received. Enter the OTP sent to '.$this->mobile.' to create your package.';
            }
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
