<?php

namespace App\Livewire\Corporate;

use App\Contracts\PaymentGateway;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\User;
use App\Support\CorporateOrderLimit;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class PackageSubscribeModal extends Component
{
    public bool $showModal = false;

    public ?int $packageId = null;

    public array $package = [];

    public array $omittedWeekdays = [5, 6]; // Fri, Sat

    public array $extraSkippedDates = [];

    public int $quantity = 1;

    public string $customerName = '';

    public string $mobile = '';

    public string $addressLine1 = '';

    public $city_id = null;

    public $area_id = null;

    public array $citiesList = [];

    public array $areasList = [];

    public array $deliveryWindows = ['12:00 PM', '11:30 AM'];

    public string $deliveryWindow = '12:00 PM';

    public bool $isConfirmingOtp = false;

    public string $otpInput = '';

    public string $paymentMethod = 'balance';

    public ?string $gatewayPaymentToken = null;

    public ?string $gatewayPaymentUrl = null;

    public array $quote = [];

    public string $errorMessage = '';

    public int $walletBalance = 0;

    #[On('open-package-subscribe')]
    public function open($packageId = null): void
    {
        $id = is_array($packageId) ? ($packageId['packageId'] ?? null) : $packageId;
        if (! $id) {
            return;
        }

        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $model = MealPackage::query()->published()->with('days.menuItem')->findOrFail($id);
        /** @var User $user */
        $user = Auth::user();

        $this->packageId = $model->id;
        $this->package = [
            'id' => $model->id,
            'name' => $model->name,
            'price_per_day' => (int) $model->price_per_day,
            'diet_tag' => $model->diet_tag,
            'start_date' => $model->start_date->toDateString(),
            'end_date' => $model->end_date->toDateString(),
            'thumbnail' => $model->thumbnail ? asset($model->thumbnail) : null,
        ];

        $this->omittedWeekdays = [5, 6];
        $this->extraSkippedDates = [];
        $this->quantity = 1;
        $this->customerName = $user->name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->addressLine1 = $user->address ?? '';
        $this->citiesList = City::all()->toArray();
        $this->city_id = $user->city_id ?? ($this->citiesList[0]['id'] ?? null);
        $this->area_id = $user->area_id;
        $this->deliveryWindow = '12:00 PM';
        $this->isConfirmingOtp = false;
        $this->otpInput = '';
        $this->paymentMethod = 'balance';
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;
        $this->errorMessage = '';
        $this->walletBalance = (int) $user->balance;

        if ($this->city_id) {
            $this->loadAreasForSelectedCity($this->city_id);
        }

        $this->refreshQuote();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->packageId = null;
        $this->package = [];
        $this->isConfirmingOtp = false;
        $this->errorMessage = '';
    }

    public function updatedCityId($value): void
    {
        $this->loadAreasForSelectedCity($value);
    }

    public function updatedOmittedWeekdays(): void
    {
        $this->omittedWeekdays = PackageBilling::normalizeOmittedWeekdays($this->omittedWeekdays);
        $this->refreshQuote();
    }

    public function updatedQuantity(): void
    {
        $this->quantity = max(1, min((int) config('middo.max_order_qty_allowed', 5), (int) $this->quantity));
        $this->refreshQuote();
    }

    public function changeQuantity(int $delta): void
    {
        $this->quantity = max(
            1,
            min((int) config('middo.max_order_qty_allowed', 5), $this->quantity + $delta)
        );
        $this->refreshQuote();
    }

    public function toggleWeekday(int $day): void
    {
        $day = (int) $day;
        if ($day < 0 || $day > 6) {
            return;
        }

        if (in_array($day, $this->omittedWeekdays, true)) {
            $this->omittedWeekdays = array_values(array_filter(
                $this->omittedWeekdays,
                fn ($d) => (int) $d !== $day
            ));
        } else {
            $this->omittedWeekdays[] = $day;
        }

        $this->omittedWeekdays = PackageBilling::normalizeOmittedWeekdays($this->omittedWeekdays);
        $this->refreshQuote();
    }

    public function toggleExtraSkip(string $date): void
    {
        if (in_array($date, $this->extraSkippedDates, true)) {
            $this->extraSkippedDates = array_values(array_filter(
                $this->extraSkippedDates,
                fn ($d) => $d !== $date
            ));
        } else {
            $this->extraSkippedDates[] = $date;
        }

        $this->refreshQuote();
    }

    protected function loadAreasForSelectedCity($cityId): void
    {
        $selectedCity = City::find($cityId);
        $this->areasList = $selectedCity ? $selectedCity->areas->toArray() : [];

        if (! empty($this->areasList)) {
            $validIds = array_column($this->areasList, 'id');
            if (! in_array($this->area_id, $validIds)) {
                $this->area_id = $this->areasList[0]['id'];
            }
        } else {
            $this->area_id = null;
        }
    }

    protected function refreshQuote(): void
    {
        if (! $this->packageId) {
            $this->quote = [];

            return;
        }

        $model = MealPackage::find($this->packageId);
        if (! $model) {
            $this->quote = [];

            return;
        }

        $this->quote = PackageBilling::quote(
            $model,
            $this->quantity,
            $this->omittedWeekdays,
            $this->extraSkippedDates
        );
        $this->walletBalance = (int) (Auth::user()?->balance ?? 0);
    }

    public function initiateConfirmation(): void
    {
        $this->errorMessage = '';
        $this->validate([
            'customerName' => 'required|string|min:2|max:120',
            'mobile' => 'required|string|min:11|max:20',
            'addressLine1' => 'required|string|min:5|max:500',
            'city_id' => 'required|exists:cities,id',
            'area_id' => 'required|exists:areas,id',
            'deliveryWindow' => 'required|in:12:00 PM,11:30 AM',
            'quantity' => 'required|integer|min:1|max:'.(int) config('middo.max_order_qty_allowed', 5),
        ]);

        $this->refreshQuote();

        if (($this->quote['billable_days'] ?? 0) < 1) {
            $this->errorMessage = 'No billable days left. Un-omit some weekdays or pick another package.';

            return;
        }

        $dates = collect($this->quote['days'] ?? [])->pluck('date')->all();
        foreach ($dates as $date) {
            if (OrderCutoff::isPastForDeliveryDate((string) $date)) {
                $this->errorMessage = OrderCutoff::placementDeniedMessage((string) $date);

                return;
            }

            if (CorporateOrderLimit::exceedsDailyLimit((int) Auth::id(), (string) $date, $this->quantity)) {
                $max = CorporateOrderLimit::maxAllowed();
                $this->errorMessage = "Daily quantity limit ({$max}) exceeded for {$date}.";

                return;
            }
        }

        $total = (int) ($this->quote['total_amount'] ?? 0);
        if ($this->paymentMethod === 'balance' && $this->walletBalance < $total) {
            $this->errorMessage = 'Insufficient Middo Balance. Top up your wallet or pay online.';

            return;
        }

        $otpResult = OrderConfirmationOtp::send($this->mobile);
        if (! ($otpResult['ok'] ?? false)) {
            $this->errorMessage = $otpResult['message'] ?? 'Could not send OTP. Try again.';

            return;
        }

        $this->isConfirmingOtp = true;
        $this->otpInput = '';
    }

    public function startGatewayPayment(): void
    {
        $this->refreshQuote();
        $total = (int) ($this->quote['total_amount'] ?? 0);
        if ($total < 1) {
            $this->errorMessage = 'Nothing to pay.';

            return;
        }

        $fingerprint = [
            'meal_package_id' => (int) $this->packageId,
            'quantity' => $this->quantity,
            'omitted_weekdays' => PackageBilling::normalizeOmittedWeekdays($this->omittedWeekdays),
            'amount' => $total,
        ];

        $checkout = app(PaymentGateway::class)->createCheckout(
            (int) Auth::id(),
            $total,
            $fingerprint
        );

        $this->gatewayPaymentToken = $checkout['token'] ?? null;
        $this->gatewayPaymentUrl = $checkout['payment_url'] ?? null;
        $this->paymentMethod = 'gateway';
    }

    public function finalizeSubscribe(): void
    {
        $this->errorMessage = '';
        $this->validate([
            'otpInput' => 'required|string|size:4',
            'customerName' => 'required|string|min:2|max:120',
            'paymentMethod' => 'required|in:balance,gateway',
        ]);

        if (! OrderConfirmationOtp::verify($this->mobile, $this->otpInput)) {
            $this->addError('otpInput', 'Invalid or expired confirmation code.');

            return;
        }

        $this->refreshQuote();

        try {
            $result = app(PackageSubscriptionService::class)->subscribe(
                Auth::user(),
                MealPackage::findOrFail($this->packageId),
                $this->quantity,
                $this->omittedWeekdays,
                $this->extraSkippedDates,
                $this->customerName,
                $this->mobile,
                $this->addressLine1,
                (int) $this->city_id,
                (int) $this->area_id,
                $this->deliveryWindow,
                $this->paymentMethod,
                $this->gatewayPaymentToken
            );
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->closeModal();
        $this->dispatch('package-subscribed');
        $this->dispatch('corporate-orders-changed');

        session()->flash('message', 'Package prepaid and scheduled — '.$result['subscription']->billable_days.' delivery days.');

        $this->redirect(route('corporates.packages.show', $result['subscription']->id), navigate: true);
    }

    public function render()
    {
        return view('livewire.corporate.package-subscribe-modal');
    }
}
