<?php

namespace App\Livewire\Corporate;

use App\Contracts\PaymentGateway;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\PackageSubscription;
use App\Models\User;
use App\Support\ChargeService;
use App\Support\CorporateOrderLimit;
use App\Support\CouponService;
use App\Support\OrderConfirmationOtp;
use App\Support\PackageBilling;
use App\Support\PackageGatewayCheckout;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class PackageSubscribeModal extends Component
{
    public bool $showModal = false;

    public ?int $packageId = null;

    public array $package = [];

    public array $menuCatalog = [];

    /** @var array<string, int> menu_item_id => day_count (string keys for Livewire) */
    public array $menuDayCounts = [];

    public array $omittedWeekdays = [5, 6]; // Fri, Sat

    public string $targetMonth = '';

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

    public bool $otpVerified = false;

    public string $otpInput = '';

    public string $paymentMethod = 'balance';

    public ?string $gatewayPaymentToken = null;

    public ?string $gatewayPaymentUrl = null;

    public array $quote = [];

    public int $chargesTotal = 0;

    /** @var list<array{name:string,amount:int,category:string}> */
    public array $chargeLines = [];

    public string $errorMessage = '';

    public string $statusMessage = '';

    public ?string $debugOtp = null;

    public int $walletBalance = 0;

    public int $selectedDays = 0;

    public int $workingDays = 0;

    public bool $fillsMonth = false;

    public array $monthOptions = [];

    public string $couponCode = '';

    public string $appliedCouponCode = '';

    public int $couponDiscount = 0;

    public string $couponMessage = '';

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

        $model = MealPackage::query()->published()->findOrFail($id);
        /** @var User $user */
        $user = Auth::user();

        $this->resetErrorBag();
        $this->packageId = (int) $model->id;
        $this->package = [
            'id' => $model->id,
            'name' => $model->name,
            'price_per_day' => (int) $model->price_per_day,
            'diet_tag' => $model->diet_tag,
            'duration_days' => (int) $model->duration_days,
            'thumbnail' => $model->thumbnail ? asset($model->thumbnail) : null,
        ];

        $this->menuCatalog = MenuItem::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'summary', 'price', 'thumbnail'])
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'summary' => $item->summary,
                'price' => (int) $item->price,
                'thumbnail' => $item->thumbnail ? asset($item->thumbnail) : null,
            ])
            ->values()
            ->all();

        $this->menuDayCounts = [];
        $this->omittedWeekdays = [5, 6];
        $this->monthOptions = $this->buildMonthOptions();
        $this->targetMonth = $this->firstAvailableMonth() ?? ($this->monthOptions[0]['value'] ?? now('Asia/Dhaka')->format('Y-m'));
        $this->quantity = 1;
        $this->customerName = $user->name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->addressLine1 = $user->address ?? '';
        $this->citiesList = City::all()->toArray();
        $this->city_id = $user->city_id ?? ($this->citiesList[0]['id'] ?? null);
        $this->area_id = $user->area_id;
        $this->deliveryWindow = '12:00 PM';
        $this->isConfirmingOtp = false;
        $this->otpVerified = false;
        $this->otpInput = '';
        $this->paymentMethod = 'balance';
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->debugOtp = null;
        $this->walletBalance = (int) $user->balance;
        $this->couponCode = '';
        $this->appliedCouponCode = '';
        $this->couponDiscount = 0;
        $this->couponMessage = '';

        if ($this->city_id) {
            $this->loadAreasForSelectedCity($this->city_id);
        }

        $this->refreshQuote();
        $this->showModal = true;
    }

    protected function buildMonthOptions(): array
    {
        $orderedMonths = PackageSubscription::orderedMonthsForUser((int) Auth::id());
        $orderedLookup = array_fill_keys($orderedMonths, true);
        $options = [];
        $cursor = now('Asia/Dhaka')->startOfMonth();

        for ($i = 0; $i < 4; $i++) {
            $value = $cursor->format('Y-m');
            $options[] = [
                'value' => $value,
                'label' => $cursor->format('F Y'),
                'locked' => isset($orderedLookup[$value]),
            ];
            $cursor->addMonth();
        }

        return $options;
    }

    protected function firstAvailableMonth(): ?string
    {
        foreach ($this->monthOptions as $option) {
            if (! ($option['locked'] ?? false)) {
                return (string) $option['value'];
            }
        }

        return null;
    }

    public function isTargetMonthLocked(): bool
    {
        foreach ($this->monthOptions as $option) {
            if (($option['value'] ?? null) === $this->targetMonth) {
                return (bool) ($option['locked'] ?? false);
            }
        }

        return PackageSubscription::userHasPackageForMonth((int) Auth::id(), $this->targetMonth);
    }

    public function selectMonth(string $month): void
    {
        if ($this->isConfirmingOtp) {
            return;
        }

        foreach ($this->monthOptions as $option) {
            if (($option['value'] ?? null) !== $month) {
                continue;
            }

            if ($option['locked'] ?? false) {
                $this->errorMessage = 'You already ordered a package for '.$option['label'].'. That month is locked.';

                return;
            }

            $this->targetMonth = $month;
            $this->errorMessage = '';
            $this->clampSelectionsToWorkingDays();
            $this->refreshQuote();

            return;
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->packageId = null;
        $this->package = [];
        $this->menuCatalog = [];
        $this->menuDayCounts = [];
        $this->quote = [];
        $this->isConfirmingOtp = false;
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->debugOtp = null;
        $this->selectedDays = 0;
        $this->workingDays = 0;
        $this->fillsMonth = false;
        $this->resetErrorBag();
    }

    public function updatedCityId($value): void
    {
        $this->loadAreasForSelectedCity($value);
        $this->refreshChargesQuote();
    }

    public function updatedAreaId(): void
    {
        $this->refreshChargesQuote();
    }

    public function updatedTargetMonth(): void
    {
        $this->errorMessage = '';

        if ($this->isTargetMonthLocked()) {
            $available = $this->firstAvailableMonth();
            $this->targetMonth = $available ?? $this->targetMonth;
            $this->errorMessage = 'That month already has a package. Choose another month.';
        }

        $this->clampSelectionsToWorkingDays();
        $this->refreshQuote();
    }

    public function updatedQuantity(): void
    {
        $this->quantity = max(1, min(CorporateOrderLimit::maxAllowed(Auth::id()), (int) $this->quantity));
        $this->refreshQuote();
    }

    public function changeQuantity(int $delta): void
    {
        $this->quantity = max(
            1,
            min(CorporateOrderLimit::maxAllowed(Auth::id()), $this->quantity + $delta)
        );
        $this->errorMessage = '';
        $this->refreshQuote();
    }

    public function changeMenuDays(int $menuItemId, int $delta): void
    {
        $this->ensureWorkingDays();

        $key = (string) $menuItemId;
        $current = (int) ($this->menuDayCounts[$key] ?? 0);
        $total = (int) collect($this->menuDayCounts)->sum(fn ($days) => (int) $days);
        $otherTotal = $total - $current;
        $maxForItem = max(0, $this->workingDays - $otherTotal);
        $next = max(0, min($maxForItem, $current + $delta));

        if ($delta > 0 && $current >= $maxForItem) {
            $this->errorMessage = 'You already selected all '.$this->workingDays.' working days this month.';
            $this->statusMessage = '';

            return;
        }

        $counts = $this->menuDayCounts;
        if ($next < 1) {
            unset($counts[$key]);
        } else {
            $counts[$key] = $next;
        }

        // Reassign so Livewire reliably detects the nested change.
        $this->menuDayCounts = $counts;
        $this->errorMessage = '';
        $this->statusMessage = '';
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
        $this->errorMessage = '';
        $this->clampSelectionsToWorkingDays();
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

    protected function selectionPayload(): array
    {
        $payload = [];
        foreach ($this->menuDayCounts as $menuItemId => $dayCount) {
            if ((int) $dayCount > 0) {
                $payload[] = [
                    'menu_item_id' => (int) $menuItemId,
                    'day_count' => (int) $dayCount,
                ];
            }
        }

        return $payload;
    }

    protected function ensureWorkingDays(): void
    {
        if ($this->workingDays > 0) {
            return;
        }

        $this->workingDays = PackageBilling::availableDatesInMonth(
            $this->targetMonth ?: now('Asia/Dhaka')->format('Y-m'),
            $this->omittedWeekdays
        )->count();
    }

    /**
     * Keep selected menu days from exceeding available working days
     * after month / off-day changes.
     */
    protected function clampSelectionsToWorkingDays(): void
    {
        $this->ensureWorkingDays();

        // Recalculate against the latest month/off-days before clamping.
        $this->workingDays = PackageBilling::availableDatesInMonth(
            $this->targetMonth ?: now('Asia/Dhaka')->format('Y-m'),
            $this->omittedWeekdays
        )->count();

        $total = (int) collect($this->menuDayCounts)->sum(fn ($days) => (int) $days);
        if ($total <= $this->workingDays) {
            return;
        }

        $remaining = $this->workingDays;
        $counts = [];
        foreach ($this->menuDayCounts as $menuItemId => $dayCount) {
            $dayCount = (int) $dayCount;
            if ($dayCount < 1 || $remaining < 1) {
                continue;
            }

            $keep = min($dayCount, $remaining);
            $counts[(string) $menuItemId] = $keep;
            $remaining -= $keep;
        }

        $this->menuDayCounts = $counts;
        if ($total > $this->workingDays) {
            $this->statusMessage = 'Selection trimmed to '.$this->workingDays.' working days for this month.';
        }
    }

    protected function refreshQuote(): void
    {
        $this->ensureWorkingDays();
        $this->selectedDays = (int) collect($this->menuDayCounts)->sum(fn ($days) => (int) $days);

        if ($this->selectedDays > $this->workingDays && $this->workingDays > 0) {
            $this->clampSelectionsToWorkingDays();
            $this->selectedDays = (int) collect($this->menuDayCounts)->sum(fn ($days) => (int) $days);
        }

        if (! $this->packageId) {
            $this->quote = [];
            $this->workingDays = 0;
            $this->fillsMonth = false;
            $this->chargesTotal = 0;
            $this->chargeLines = [];

            return;
        }

        $model = MealPackage::find($this->packageId);
        if (! $model) {
            $this->quote = [];
            $this->workingDays = 0;
            $this->fillsMonth = false;
            $this->chargesTotal = 0;
            $this->chargeLines = [];

            return;
        }

        try {
            $this->quote = PackageBilling::quoteFromSelections(
                $model,
                $this->quantity,
                $this->selectionPayload(),
                $this->omittedWeekdays,
                $this->targetMonth ?: now('Asia/Dhaka')->format('Y-m')
            );
            $this->workingDays = (int) ($this->quote['available_days'] ?? 0);
            $this->selectedDays = (int) ($this->quote['billable_days'] ?? $this->selectedDays);
            $this->fillsMonth = (bool) ($this->quote['fills_month'] ?? false);
        } catch (\Throwable $e) {
            $this->workingDays = PackageBilling::availableDatesInMonth(
                $this->targetMonth ?: now('Asia/Dhaka')->format('Y-m'),
                $this->omittedWeekdays
            )->count();
            $this->fillsMonth = false;
            $this->errorMessage = $e->getMessage();
        }

        $this->refreshChargesQuote();
        $this->walletBalance = (int) (Auth::user()?->balance ?? 0);
        $this->revalidateAppliedCoupon();
    }

    public function payableTotal(): int
    {
        $subtotal = (int) ($this->quote['total_amount'] ?? 0);

        return max(0, $subtotal - $this->couponDiscount) + $this->chargesTotal;
    }

    public function applyCoupon(): void
    {
        $this->couponMessage = '';
        $this->errorMessage = '';
        $this->refreshQuote();
        $subtotal = (int) ($this->quote['total_amount'] ?? 0);

        try {
            $quoted = app(CouponService::class)->quote(
                $this->couponCode,
                Auth::user(),
                \App\Models\CouponRedemption::CONTEXT_PACKAGE,
                $subtotal
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->appliedCouponCode = '';
            $this->couponDiscount = 0;
            $this->couponMessage = collect($e->validator->errors()->all())->implode(' ');
            $this->addError('couponCode', $this->couponMessage);

            return;
        }

        $this->appliedCouponCode = $quoted['coupon']->code;
        $this->couponCode = $quoted['coupon']->code;
        $this->couponDiscount = (int) $quoted['discount_amount'];
        $this->couponMessage = 'Coupon '.$quoted['coupon']->code.' applied (−৳'.number_format($this->couponDiscount).').';
        $this->resetErrorBag('couponCode');
    }

    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->appliedCouponCode = '';
        $this->couponDiscount = 0;
        $this->couponMessage = '';
        $this->resetErrorBag('couponCode');
    }

    protected function revalidateAppliedCoupon(): void
    {
        if ($this->appliedCouponCode === '') {
            $this->couponDiscount = 0;

            return;
        }

        $subtotal = (int) ($this->quote['total_amount'] ?? 0);
        if ($subtotal < 1) {
            $this->removeCoupon();

            return;
        }

        try {
            $quoted = app(CouponService::class)->quote(
                $this->appliedCouponCode,
                Auth::user(),
                \App\Models\CouponRedemption::CONTEXT_PACKAGE,
                $subtotal
            );
            $this->couponDiscount = (int) $quoted['discount_amount'];
            $this->appliedCouponCode = $quoted['coupon']->code;
        } catch (\Throwable $e) {
            $this->removeCoupon();
            $this->couponMessage = $e instanceof \Illuminate\Validation\ValidationException
                ? collect($e->validator->errors()->all())->implode(' ')
                : 'Coupon removed because it no longer applies.';
        }
    }

    protected function refreshChargesQuote(): void
    {
        if (! $this->area_id || empty($this->quote['selections'])) {
            $this->chargesTotal = 0;
            $this->chargeLines = [];

            return;
        }

        $quote = app(ChargeService::class)->quotePackage(
            (int) $this->area_id,
            (int) $this->quantity,
            $this->selectionPayload()
        );

        $this->chargesTotal = (int) ($quote['total'] ?? 0);
        $this->chargeLines = collect($quote['lines'] ?? [])
            ->map(fn ($line) => [
                'name' => (string) ($line['name'] ?? 'Charge'),
                'amount' => (int) ($line['amount'] ?? 0),
                'category' => (string) ($line['category'] ?? 'other'),
            ])
            ->values()
            ->all();
    }

    public function updatedErrorMessage(string $value): void
    {
        if ($value !== '') {
            $this->scrollFeedbackIntoView();
        }
    }

    protected function scrollFeedbackIntoView(): void
    {
        $this->js(<<<'JS'
            queueMicrotask(() => {
                document.getElementById('pkg-modal-feedback')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        JS);
    }

    public function canPayWithWallet(): bool
    {
        $total = $this->payableTotal();

        return $this->walletBalance > 0 && $total > 0 && $this->walletBalance >= $total;
    }

    public function payWithWallet(): void
    {
        $this->paymentMethod = 'balance';
        $this->initiateConfirmation();
    }

    public function resendOtp(): void
    {
        $this->errorMessage = '';
        $this->otpVerified = false;
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;

        if ($this->paymentMethod === 'gateway') {
            $this->startGatewayPayment();

            return;
        }

        $this->payWithWallet();
    }

    public function initiateConfirmation(): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->resetErrorBag();
        $this->refreshQuote();

        try {
            $this->validate([
                'customerName' => 'required|string|min:2|max:120',
                'mobile' => 'required|string|min:11|max:20',
                'addressLine1' => 'required|string|min:5|max:500',
                'city_id' => 'required|exists:cities,id',
                'area_id' => 'required|exists:areas,id',
                'deliveryWindow' => 'required|in:12:00 PM,11:30 AM',
                'quantity' => 'required|integer|min:1|max:'.CorporateOrderLimit::maxAllowed(Auth::id()),
                'targetMonth' => 'required|date_format:Y-m',
            ]);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->validator->errors()->all())->implode(' ');
            $this->setErrorBag($e->validator->errors());

            return;
        }

        try {
            PackageBilling::assertSelectionsFillMonth($this->quote);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        if ($this->isTargetMonthLocked()) {
            $this->errorMessage = 'You already ordered a package for this month. Choose another month.';

            return;
        }

        if ($this->paymentMethod === 'balance' && $this->payableTotal() > 0 && ! $this->canPayWithWallet()) {
            $this->errorMessage = 'Insufficient Middo Balance. Top up your wallet or pay online.';

            return;
        }

        $otpResult = OrderConfirmationOtp::send($this->mobile);
        if (! ($otpResult['ok'] ?? false)) {
            $this->errorMessage = $otpResult['message'] ?? 'Could not send OTP. Try again.';

            return;
        }

        $this->isConfirmingOtp = true;
        $this->otpVerified = false;
        $this->otpInput = '';
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;
        $this->debugOtp = isset($otpResult['debug_otp']) ? (string) $otpResult['debug_otp'] : null;
        $this->statusMessage = $this->debugOtp
            ? 'OTP sent. Debug code: '.$this->debugOtp
            : 'OTP sent to '.$this->mobile.'. Enter the 4-digit code to confirm.';
    }

    /**
     * Online payment: send OTP first (same as menu checkout). Gateway session starts after verify.
     */
    public function startGatewayPayment(): void
    {
        $this->paymentMethod = 'gateway';
        $this->initiateConfirmation();
    }

    /**
     * Gateway path: verify OTP, then create payment session for Make payment.
     */
    public function verifyGatewayOtp(): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->resetErrorBag();
        $this->otpInput = trim((string) $this->otpInput);

        try {
            $this->validate([
                'otpInput' => 'required|string|size:4',
            ]);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->validator->errors()->all())->implode(' ');
            $this->setErrorBag($e->validator->errors());

            return;
        }

        if ($this->paymentMethod !== 'gateway') {
            $this->finalizeSubscribe();

            return;
        }

        if (! OrderConfirmationOtp::verify($this->mobile, $this->otpInput)) {
            $this->errorMessage = 'Invalid or expired confirmation code. Request a new OTP and try again.';
            $this->addError('otpInput', 'Invalid or expired confirmation code.');

            return;
        }

        $this->refreshQuote();

        try {
            PackageBilling::assertSelectionsFillMonth($this->quote);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        if ($this->isTargetMonthLocked()) {
            $this->errorMessage = 'You already ordered a package for this month. Choose another month.';

            return;
        }

        $total = $this->payableTotal();
        if ($total < 1) {
            // Fully discounted — create via wallet/OTP finalize path.
            $this->paymentMethod = 'balance';
            $this->otpVerified = true;
            $this->finalizeSubscribe();

            return;
        }

        $draft = [
            'customer_name' => $this->customerName,
            'mobile' => $this->mobile,
            'address_line1' => $this->addressLine1,
            'city_id' => (int) $this->city_id,
            'area_id' => (int) $this->area_id,
            'delivery_window' => $this->deliveryWindow,
            'coupon_code' => $this->appliedCouponCode !== '' ? $this->appliedCouponCode : null,
        ];

        try {
            $checkout = PackageGatewayCheckout::start(
                Auth::user(),
                (int) $this->packageId,
                $this->quantity,
                $this->omittedWeekdays,
                $this->targetMonth,
                $this->selectionPayload(),
                $total,
                $draft
            );
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = $e->getMessage() ?: 'Could not start online payment. Try again.';

            return;
        }

        $token = (string) ($checkout['token'] ?? '');
        $paymentUrl = (string) ($checkout['payment_url'] ?? '');
        if ($token === '' || $paymentUrl === '') {
            $this->errorMessage = 'Could not start online payment. Try again.';

            return;
        }

        $this->gatewayPaymentToken = $token;
        $this->gatewayPaymentUrl = $paymentUrl;
        $this->otpVerified = true;
        $this->statusMessage = 'Code verified. Complete payment to create your package.';
    }

    /**
     * Poll after Make payment: create package as soon as the gateway marks the session paid.
     */
    public function checkGatewayPaymentCompletion()
    {
        if ($this->paymentMethod !== 'gateway'
            || ! $this->otpVerified
            || ! filled($this->gatewayPaymentToken)) {
            return null;
        }

        $result = PackageGatewayCheckout::completeIfPaid($this->gatewayPaymentToken);
        if (! ($result['ok'] ?? false)) {
            return null;
        }

        $subscriptionId = (int) ($result['subscription_id'] ?? 0);
        session()->flash('message', $result['message'] ?? 'Package prepaid successfully.');

        if ($subscriptionId > 0) {
            return $this->redirect(route('corporates.packages.show', ['subscriptionId' => $subscriptionId]));
        }

        return $this->redirect(route('corporates.packages.index'));
    }

    public function cancelConfirmation(): void
    {
        $this->isConfirmingOtp = false;
        $this->otpVerified = false;
        $this->otpInput = '';
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->debugOtp = null;
        $this->resetErrorBag();
    }

    public function finalizeSubscribe(): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';
        $this->resetErrorBag();
        $this->otpInput = trim((string) $this->otpInput);

        // Already verified in verifyGatewayOtp before flipping to balance for free carts.
        $skipOtp = $this->otpVerified && $this->paymentMethod === 'balance';

        try {
            $rules = [
                'customerName' => 'required|string|min:2|max:120',
                'paymentMethod' => 'required|in:balance',
                'city_id' => 'required|exists:cities,id',
                'area_id' => 'required|exists:areas,id',
                'addressLine1' => 'required|string|min:5|max:500',
                'targetMonth' => 'required|date_format:Y-m',
            ];
            if (! $skipOtp) {
                $rules['otpInput'] = 'required|string|size:4';
            }
            $this->validate($rules);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->validator->errors()->all())->implode(' ');
            $this->setErrorBag($e->validator->errors());

            return;
        }

        if (! $skipOtp && ! OrderConfirmationOtp::verify($this->mobile, $this->otpInput)) {
            $this->errorMessage = 'Invalid or expired confirmation code. Request a new OTP and try again.';
            $this->addError('otpInput', 'Invalid or expired confirmation code.');
            $this->isConfirmingOtp = true;

            return;
        }

        $this->refreshQuote();

        try {
            PackageBilling::assertSelectionsFillMonth($this->quote);

            $result = app(PackageSubscriptionService::class)->subscribe(
                Auth::user(),
                MealPackage::findOrFail($this->packageId),
                $this->quantity,
                $this->omittedWeekdays,
                $this->selectionPayload(),
                $this->targetMonth,
                $this->customerName,
                $this->mobile,
                $this->addressLine1,
                (int) $this->city_id,
                (int) $this->area_id,
                $this->deliveryWindow,
                'balance',
                null,
                $this->appliedCouponCode !== '' ? $this->appliedCouponCode : null
            );
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = $e->getMessage() ?: 'Could not create the package. Please try again.';
            $this->isConfirmingOtp = true;

            return;
        }

        $subscriptionId = $result['subscription']->id;
        $days = (int) $result['subscription']->billable_days;

        session()->flash(
            'message',
            'Package prepaid for '.$days.' days. Middo operations will assign exact delivery dates next.'
        );

        // Full-page redirect. Closing the nested modal / dispatching parent refresh
        // before redirect was aborting confirmation so the UI looked stuck.
        $this->redirect(route('corporates.packages.show', ['subscriptionId' => $subscriptionId]));
    }

    public function render()
    {
        return view('livewire.corporate.package-subscribe-modal');
    }
}
