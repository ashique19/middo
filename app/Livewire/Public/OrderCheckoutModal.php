<?php

namespace App\Livewire\Public;

use App\Contracts\PaymentGateway;
use App\Models\Area;
use App\Models\City;
use App\Models\CouponRedemption;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\ChargeService;
use App\Support\CorporateOrderLimit;
use App\Support\CorporateOrderPrepayment;
use App\Support\CouponService;
use App\Support\MealOrderGrouper;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderCutoff;
use App\Support\OrderPaymentMethod;
use App\Support\WalletLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderCheckoutModal extends Component
{
    public bool $showModal = false;

    public array $dish = [];

    // Cut-off mirrors config/middo.php via OrderCutoff
    public int $cutoffHour = 15;

    public int $cutoffMinute = 28;

    // Checkout Form States
    public string $customerName = 'User';

    public array $quantities = [];

    public array $availableDates = [];

    public bool $isPastCutoff = false;

    public string $selectedDate = '';

    public array $deliveryWindows = ['12:00 PM', '11:30 AM'];

    public string $deliveryWindow = '12:00 PM';

    public string $addressLine1 = '';

    // Database Relational Select States
    public $city_id = null;

    public $area_id = null;

    public string $mobile = '';

    // Step Flag & Verification
    public bool $isConfirmingOtp = false;

    public string $otpInput = '';

    public string $paymentMethod = 'cash_on_delivery';

    public array $prepayment = [];

    public ?string $gatewayPaymentToken = null;

    public ?string $gatewayPaymentUrl = null;

    // Dynamic Arrays from Database (Converted to arrays to avoid Dehydration/Hydration crashes)
    public array $citiesList = [];

    public array $areasList = [];

    // Financial Summaries
    public float $subtotal = 0.0;

    public float $taxesAndFees = 0.00;

    public float $total = 0.0;

    public string $couponCode = '';

    public string $appliedCouponCode = '';

    public int $couponDiscount = 0;

    public string $couponMessage = '';

    /** @var list<array{name:string,amount:int,category:string}> */
    public array $chargeLines = [];

    /**
     * Component boot initialization.
     */
    public function mount(): void
    {
        $this->cutoffHour = OrderCutoff::hour();
        $this->cutoffMinute = OrderCutoff::minute();
        $this->citiesList = City::all()->toArray();

        if (Auth::check()) {
            $user = Auth::user();
            // Buyer = individual worker; company is org-only (not the receiver default).
            $this->customerName = $user->name ?? 'User';
            $this->addressLine1 = $user->address ?? '';
            $this->city_id = $user->city_id ?? (! empty($this->citiesList) ? $this->citiesList[0]['id'] : null);
            $this->area_id = $user->area_id;
            $this->mobile = $user->mobile ?? '';

            if ($this->city_id) {
                $this->loadAreasForSelectedCity($this->city_id);
            }
        }
    }

    #[On('openOrderModal')]
    public function loadOrderCheckout($dishId): void
    {
        $id = is_array($dishId) ? ($dishId['dishId'] ?? null) : $dishId;
        if (! $id) {
            return;
        }

        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        // Re-load basic arrays to guarantee fresh data structures
        $this->citiesList = City::all()->toArray();
        $user = Auth::user();
        $this->customerName = $user->name ?? 'User';
        $this->dish = MenuItem::findOrFail($id)->toArray();

        $this->cutoffHour = OrderCutoff::hour();
        $this->cutoffMinute = OrderCutoff::minute();
        $dhakaNow = now(OrderCutoff::timezone());
        $this->isPastCutoff = OrderCutoff::isPastForDeliveryDate($dhakaNow);

        $this->availableDates = [];
        $this->quantities = [];
        $startOffset = $this->isPastCutoff ? 1 : 0;

        for ($i = $startOffset; $i < ($startOffset + 9); $i++) {
            $dateString = $dhakaNow->copy()->addDays($i)->format('Y-m-d');
            $this->availableDates[] = $dateString;
            $this->quantities[$dateString] = ($i === $startOffset) ? 1 : 0;
        }

        $this->selectedDate = $this->availableDates[0] ?? now()->setTimezone('Asia/Dhaka')->format('Y-m-d');

        $this->addressLine1 = $user->address ?? '';
        $this->city_id = $user->city_id ?? (! empty($this->citiesList) ? $this->citiesList[0]['id'] : null);
        $this->area_id = $user->area_id;
        $this->mobile = $user->mobile ?? '';

        $this->isConfirmingOtp = false;
        $this->otpInput = '';
        $this->paymentMethod = OrderPaymentMethod::CASH_ON_DELIVERY;
        $this->prepayment = [];
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;
        $this->couponCode = '';
        $this->appliedCouponCode = '';
        $this->couponDiscount = 0;
        $this->couponMessage = '';

        if ($this->city_id) {
            $this->loadAreasForSelectedCity($this->city_id);
        }

        $this->recalculateTotals();
        $this->refreshPrepaymentQuote();

        // Explicitly set modal visibility true at the very end of processing
        $this->showModal = true;
    }

    /**
     * Triggers automatically when the user mutates the City Dropdown select element.
     */
    public function updatedCityId($value): void
    {
        $this->loadAreasForSelectedCity($value);
        $this->recalculateTotals();
    }

    public function updatedAreaId(): void
    {
        $this->recalculateTotals();
    }

    /**
     * Queries relation context directly via City model mapping.
     */
    protected function loadAreasForSelectedCity($cityId): void
    {
        $selectedCity = City::find($cityId);
        $this->areasList = $selectedCity ? $selectedCity->areas->toArray() : [];

        // Reset or default area choice safely if current selection falls out of scope
        if (! empty($this->areasList)) {
            $validIds = array_column($this->areasList, 'id');
            if (! in_array($this->area_id, $validIds)) {
                $this->area_id = $this->areasList[0]['id'];
            }
        } else {
            $this->area_id = null;
        }
    }

    /**
     * Calculates bill pricing matrices instantly on the user layout stream.
     */
    protected function recalculateTotals(): void
    {
        if (! $this->dish) {
            return;
        }

        $totalItemsCount = array_sum($this->quantities);
        $this->subtotal = (float) ($this->dish['price'] ?? 0) * $totalItemsCount;

        $quote = app(ChargeService::class)->quoteOrderCart(
            $this->area_id ? (int) $this->area_id : null,
            (int) ($this->dish['id'] ?? 0),
            $this->quantities
        );
        $this->taxesAndFees = (float) ($quote['total'] ?? 0);
        $this->chargeLines = collect($quote['lines'] ?? [])
            ->map(fn ($line) => [
                'name' => (string) ($line['name'] ?? 'Charge'),
                'amount' => (int) ($line['amount'] ?? 0),
                'category' => (string) ($line['category'] ?? 'other'),
            ])
            ->values()
            ->all();

        $this->revalidateAppliedCoupon();
        $this->total = max(0, $this->subtotal + $this->taxesAndFees - $this->couponDiscount);
        $this->refreshPrepaymentQuote();
    }

    public function applyCoupon(): void
    {
        $this->couponMessage = '';
        $quoteBase = (int) round($this->subtotal + $this->taxesAndFees);

        try {
            $quoted = app(CouponService::class)->quote(
                $this->couponCode,
                Auth::user(),
                CouponRedemption::CONTEXT_ORDER,
                $quoteBase
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->appliedCouponCode = '';
            $this->couponDiscount = 0;
            $this->couponMessage = collect($e->validator->errors()->all())->implode(' ');
            $this->addError('couponCode', $this->couponMessage);
            $this->total = max(0, $this->subtotal + $this->taxesAndFees);
            $this->refreshPrepaymentQuote();

            return;
        }

        $this->appliedCouponCode = $quoted['coupon']->code;
        $this->couponCode = $quoted['coupon']->code;
        $this->couponDiscount = (int) $quoted['discount_amount'];
        $this->couponMessage = 'Coupon '.$quoted['coupon']->code.' applied (−৳'.number_format($this->couponDiscount).').';
        $this->resetErrorBag('couponCode');
        $this->total = max(0, $this->subtotal + $this->taxesAndFees - $this->couponDiscount);
        $this->refreshPrepaymentQuote();
    }

    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->appliedCouponCode = '';
        $this->couponDiscount = 0;
        $this->couponMessage = '';
        $this->resetErrorBag('couponCode');
        $this->total = max(0, $this->subtotal + $this->taxesAndFees);
        $this->refreshPrepaymentQuote();
    }

    protected function revalidateAppliedCoupon(): void
    {
        if ($this->appliedCouponCode === '') {
            $this->couponDiscount = 0;

            return;
        }

        $quoteBase = (int) round($this->subtotal + $this->taxesAndFees);
        if ($quoteBase < 1) {
            $this->appliedCouponCode = '';
            $this->couponDiscount = 0;

            return;
        }

        try {
            $quoted = app(CouponService::class)->quote(
                $this->appliedCouponCode,
                Auth::user(),
                CouponRedemption::CONTEXT_ORDER,
                $quoteBase
            );
            $this->couponDiscount = (int) $quoted['discount_amount'];
            $this->appliedCouponCode = $quoted['coupon']->code;
        } catch (\Throwable $e) {
            $this->appliedCouponCode = '';
            $this->couponDiscount = 0;
            $this->couponMessage = $e instanceof \Illuminate\Validation\ValidationException
                ? collect($e->validator->errors()->all())->implode(' ')
                : 'Coupon removed because it no longer applies.';
        }
    }

    public function updatedCustomerName(): void
    {
        $this->refreshPrepaymentQuote();
    }

    public function updatedMobile(): void
    {
        $this->refreshPrepaymentQuote();
    }

    protected function refreshPrepaymentQuote(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || empty($this->dish)) {
            $this->prepayment = [];

            return;
        }

        $activeDates = array_filter($this->quantities, fn ($qty) => $qty > 0);
        $cartTotal = 0;
        foreach ($activeDates as $qty) {
            $cartTotal += (int) round(($this->dish['price'] ?? 0) * (int) $qty);
        }
        $cartTotal += (int) round($this->taxesAndFees);
        $cartTotal = max(0, $cartTotal - $this->couponDiscount);

        $this->prepayment = CorporateOrderPrepayment::evaluate(
            $user,
            $this->customerName,
            $this->mobile !== '' ? $this->mobile : (string) $user->mobile,
            count($activeDates),
            $cartTotal
        );

        $this->syncDefaultPaymentMethod(count($activeDates));
    }

    protected function syncDefaultPaymentMethod(int $activeDateCount): void
    {
        $prepayRequired = (bool) ($this->prepayment['required'] ?? false);
        $options = OrderPaymentMethod::checkoutOptions(
            $prepayRequired,
            $activeDateCount,
            $this->walletBalance(),
            $this->balanceChargeAmount()
        );

        if (! in_array($this->paymentMethod, $options, true)) {
            $this->paymentMethod = $options[0] ?? OrderPaymentMethod::GATEWAY;
        }
    }

    public function getCodAllowedProperty(): bool
    {
        $activeDates = array_filter($this->quantities, fn ($qty) => $qty > 0);

        return OrderPaymentMethod::allowsCashOnDelivery(
            (bool) ($this->prepayment['required'] ?? false),
            count($activeDates)
        );
    }

    public function getShowsPaymentMethodPickerProperty(): bool
    {
        // Always show the picker so COD / Balance / Online stay visible; unavailable
        // methods are disabled in the template rather than hidden.
        return true;
    }

    public function getBalancePaymentAvailableProperty(): bool
    {
        return OrderPaymentMethod::balanceSelectable(
            $this->walletBalance(),
            $this->balanceChargeAmount()
        );
    }

    protected function walletBalance(): int
    {
        return (int) (Auth::user()?->balance ?? 0);
    }

    /**
     * Amount Middo Balance would debit if selected (full cart, or required prepay).
     */
    protected function balanceChargeAmount(): int
    {
        $prepayRequired = (bool) ($this->prepayment['required'] ?? false);

        return OrderPaymentMethod::checkoutChargeAmount(
            OrderPaymentMethod::BALANCE,
            $prepayRequired,
            (int) ($this->prepayment['amount'] ?? 0),
            (int) round($this->total)
        );
    }

    /**
     * Toggles a date between active (qty 1) and inactive (qty 0)
     */
    public function toggleDateSelection(string $dateString): void
    {
        if ($this->isConfirmingOtp) {
            return;
        }

        $currentQty = $this->quantities[$dateString] ?? 0;
        if ($currentQty > 0) {
            $this->quantities[$dateString] = 0;
        } else {
            if ($this->remainingQtyForDate($dateString) < 1) {
                $this->addError('quantities', $this->dailyLimitMessage($dateString));

                return;
            }

            $this->quantities[$dateString] = 1;
            $this->selectedDate = $dateString;
        }
        $this->recalculateTotals();
    }

    /**
     * Modifies individual date item quantities via inline counter elements.
     */
    public function changeDateQuantity(string $date, int $amount): void
    {
        if ($this->isConfirmingOtp || ! isset($this->quantities[$date]) || $this->quantities[$date] === 0) {
            return;
        }

        // Pressing "-" at quantity 1 deselects the date (same as tap toggle).
        if ($amount < 0 && $this->quantities[$date] <= 1) {
            $this->quantities[$date] = 0;
            $this->recalculateTotals();

            return;
        }

        $maxQty = $this->remainingQtyForDate($date);
        $this->quantities[$date] = max(1, min($maxQty, $this->quantities[$date] + $amount));
        $this->recalculateTotals();
    }

    public function remainingQtyForDate(string $date): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return CorporateOrderLimit::remainingQtyForDate(Auth::id(), $date);
    }

    protected function validateDailyQuantities(array $activeOrders): bool
    {
        foreach ($activeOrders as $date => $qty) {
            if (CorporateOrderLimit::exceedsDailyLimit(Auth::id(), $date, $qty)) {
                $this->addError('quantities', $this->dailyLimitMessage($date));

                return false;
            }
        }

        return true;
    }

    protected function dailyLimitMessage(string $date): string
    {
        $formattedDate = Carbon::parse($date)->format('M d, Y');
        $max = CorporateOrderLimit::maxAllowed();
        $remaining = $this->remainingQtyForDate($date);

        return "Maximum {$max} meals allowed per day on {$formattedDate}. You can order up to {$remaining} more.";
    }

    /**
     * Step 1: Validates entries against strict local numbers and fires verification token.
     */
    public function initiateOrderConfirmation()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $this->validate([
            'customerName' => 'required|string|min:2|max:120',
            'addressLine1' => 'required|string|min:5|max:255',
            'city_id' => 'required|exists:cities,id',
            'area_id' => 'required|exists:areas,id',
            'mobile' => 'required|string|regex:/^01[3-9]\d{8}$/',
            'quantities' => 'required|array',
            'quantities.*' => 'required|integer|min:0|max:'.CorporateOrderLimit::maxAllowed(),
        ], [
            'customerName.required' => 'Please provide the receiver name.',
            'addressLine1.required' => 'Please provide your specific street address details.',
            'city_id.required' => 'Please select a delivery city.',
            'area_id.required' => 'Please select an area location.',
            'mobile.required' => 'Mobile number is required to receive confirmation OTP.',
            'mobile.regex' => 'Provide a valid 11-digit mobile number format (e.g., 01710123456).',
        ]);

        $activeOrders = array_filter($this->quantities, fn ($qty) => $qty > 0);
        if (empty($activeOrders)) {
            $this->addError('quantities', 'Please select a quantity for at least one delivery date.');

            return;
        }

        if (! $this->validateDailyQuantities($activeOrders)) {
            return;
        }

        $this->refreshPrepaymentQuote();

        $activeDateCount = count($activeOrders);
        $this->syncDefaultPaymentMethod($activeDateCount);

        $resolved = $this->resolveCheckoutPaymentMethod($this->prepayment, $activeDateCount);
        if ($resolved === null) {
            return;
        }
        $this->paymentMethod = $resolved;

        $cartTotal = 0;
        foreach ($activeOrders as $qty) {
            $cartTotal += (int) round(($this->dish['price'] ?? 0) * (int) $qty);
        }
        $cartTotal += (int) round($this->taxesAndFees);
        $this->revalidateAppliedCoupon();
        $cartTotal = max(0, $cartTotal - $this->couponDiscount);
        $chargeAmount = $this->checkoutChargeAmount($this->prepayment, $cartTotal);

        if ($chargeAmount > 0
            && $this->paymentMethod === OrderPaymentMethod::BALANCE
            && (int) Auth::user()->balance < $chargeAmount) {
            $this->addError(
                'paymentMethod',
                $this->prepayment['message'] ?? 'Insufficient Middo Balance. Choose online pay, Cash on Delivery, or top up.'
            );

            return;
        }

        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;

        if ($chargeAmount > 0 && $this->paymentMethod === OrderPaymentMethod::GATEWAY) {
            $fingerprint = [
                'menu_item_id' => (int) ($this->dish['id'] ?? 0),
                'receiver_name' => CorporateOrderPrepayment::normalizeName($this->customerName),
                'mobile' => CorporateOrderPrepayment::normalizeMobile($this->mobile),
                'dates' => collect($activeOrders)->map(fn ($qty, $date) => [
                    'date' => $date,
                    'quantity' => (int) $qty,
                ])->values()->all(),
                'amount' => $chargeAmount,
            ];

            $checkout = app(PaymentGateway::class)->createCheckout(
                (int) Auth::id(),
                $chargeAmount,
                $fingerprint
            );

            $this->gatewayPaymentToken = $checkout['token'];
            $this->gatewayPaymentUrl = $checkout['payment_url'];
        }

        $result = OrderConfirmationOtp::send($this->mobile);

        if (! ($result['ok'] ?? false)) {
            $this->addError('mobile', $result['message'] ?? 'SMS channel transmission error. Please retry.');

            return;
        }

        $this->isConfirmingOtp = true;
        session()->flash('order_status', $result['message'] ?? 'Confirmation code sent successfully.');
    }

    /**
     * Step 2: Finalizes verification token confirmation and creates individual orders.
     */
    public function finalizeOrder()
    {
        $this->validate([
            'otpInput' => 'required|string|size:4',
            'customerName' => 'required|string|min:2|max:120',
            'paymentMethod' => 'nullable|in:balance,gateway,cash_on_delivery',
        ]);

        if (! OrderConfirmationOtp::verify($this->mobile, $this->otpInput)) {
            $this->addError('otpInput', 'Invalid or expired confirmation token code.');

            return;
        }

        $activeOrders = array_filter($this->quantities, fn ($qty) => $qty > 0);

        if (empty($activeOrders)) {
            $this->addError('quantities', 'Please select a quantity for at least one delivery date.');

            return;
        }

        if (! $this->validateDailyQuantities($activeOrders)) {
            return;
        }

        foreach (array_keys($activeOrders) as $date) {
            if (OrderCutoff::isPastForDeliveryDate((string) $date)) {
                $this->addError('quantities', OrderCutoff::placementDeniedMessage((string) $date));

                return;
            }
        }

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $this->refreshPrepaymentQuote();
        $prepayment = $this->prepayment;

        $resolvedPaymentMethod = $this->resolveCheckoutPaymentMethod($prepayment, count($activeOrders));
        if ($resolvedPaymentMethod === null) {
            return;
        }
        $this->paymentMethod = $resolvedPaymentMethod;

        if ($prepayment['required'] ?? false) {
            if ($this->paymentMethod === OrderPaymentMethod::BALANCE && ! ($prepayment['balance_sufficient'] ?? false)) {
                $this->addError('paymentMethod', 'Insufficient Middo Balance for required prepayment.');

                return;
            }

            if ($this->paymentMethod === OrderPaymentMethod::GATEWAY) {
                if (! filled($this->gatewayPaymentToken)) {
                    $this->addError('paymentMethod', 'Start online payment before confirming the order.');

                    return;
                }

                $fingerprint = [
                    'menu_item_id' => (int) ($this->dish['id'] ?? 0),
                    'receiver_name' => CorporateOrderPrepayment::normalizeName($this->customerName),
                    'mobile' => CorporateOrderPrepayment::normalizeMobile($this->mobile),
                    'dates' => collect($activeOrders)->map(fn ($qty, $date) => [
                        'date' => $date,
                        'quantity' => (int) $qty,
                    ])->values()->all(),
                    'amount' => (int) $prepayment['amount'],
                ];

                $consumed = app(PaymentGateway::class)->consumePaid(
                    $this->gatewayPaymentToken,
                    (int) $currentUser->id,
                    (int) $prepayment['amount'],
                    $fingerprint
                );

                if (! ($consumed['ok'] ?? false)) {
                    $this->addError('paymentMethod', $consumed['message'] ?? 'Complete online payment first.');

                    return;
                }
            }
        }

        $lineTotals = [];
        $chargeQuote = app(ChargeService::class)->quoteOrderCart(
            $this->area_id ? (int) $this->area_id : null,
            (int) ($this->dish['id'] ?? 0),
            $activeOrders
        );
        $perOrderCharges = $chargeQuote['per_order'] ?? [];

        foreach ($activeOrders as $date => $qty) {
            $food = (int) round(($this->dish['price'] ?? 0) * (int) $qty);
            $fees = (int) collect($perOrderCharges[(string) $date] ?? [])->sum('amount');
            $lineTotals[] = $food + $fees;
        }
        $cartTotal = (int) array_sum($lineTotals);
        $this->revalidateAppliedCoupon();
        $discountAmount = min($this->couponDiscount, $cartTotal);
        $payableCart = max(0, $cartTotal - $discountAmount);
        $chargeAmount = $this->checkoutChargeAmount($prepayment, $payableCart);
        // Allocate coupon first, then split prepaid across post-discount line nets so
        // no line is overpaid relative to what the customer still owes on that day.
        $discountShares = app(CouponService::class)->allocateDiscount($lineTotals, $discountAmount);
        $netLineTotals = [];
        foreach ($lineTotals as $i => $gross) {
            $netLineTotals[] = max(0, (int) $gross - (int) ($discountShares[$i] ?? 0));
        }
        $allocations = CorporateOrderPrepayment::allocate($chargeAmount, $netLineTotals);
        $couponId = null;
        if ($this->appliedCouponCode !== '' && $discountAmount > 0) {
            try {
                $couponId = app(CouponService::class)
                    ->findApplicable($this->appliedCouponCode, $currentUser, CouponRedemption::CONTEXT_ORDER, $cartTotal)
                    ->id;
            } catch (\Throwable $e) {
                $this->addError('couponCode', $e instanceof \Illuminate\Validation\ValidationException
                    ? collect($e->validator->errors()->all())->implode(' ')
                    : 'Coupon could not be applied.');

                return;
            }
        }
        $profileMatches = CorporateOrderPrepayment::profileMatchesReceiver(
            $currentUser,
            $this->customerName,
            $this->mobile
        );

        // Optional full pay via gateway when COD is allowed and user chose online.
        if ($chargeAmount > 0
            && $this->paymentMethod === OrderPaymentMethod::GATEWAY
            && ! ($prepayment['required'] ?? false)) {
            if (! filled($this->gatewayPaymentToken)) {
                $this->addError('paymentMethod', 'Start online payment before confirming the order.');

                return;
            }

            $fingerprint = [
                'menu_item_id' => (int) ($this->dish['id'] ?? 0),
                'receiver_name' => CorporateOrderPrepayment::normalizeName($this->customerName),
                'mobile' => CorporateOrderPrepayment::normalizeMobile($this->mobile),
                'dates' => collect($activeOrders)->map(fn ($qty, $date) => [
                    'date' => $date,
                    'quantity' => (int) $qty,
                ])->values()->all(),
                'amount' => $chargeAmount,
            ];

            $consumed = app(PaymentGateway::class)->consumePaid(
                $this->gatewayPaymentToken,
                (int) $currentUser->id,
                $chargeAmount,
                $fingerprint
            );

            if (! ($consumed['ok'] ?? false)) {
                $this->addError('paymentMethod', $consumed['message'] ?? 'Complete online payment first.');

                return;
            }
        }

        if ($chargeAmount > 0
            && $this->paymentMethod === OrderPaymentMethod::BALANCE
            && (int) $currentUser->balance < $chargeAmount) {
            $this->addError('paymentMethod', 'Insufficient Middo Balance for this payment.');

            return;
        }

        $paymentMethod = $this->paymentMethod;

        DB::transaction(function () use (
            $activeOrders,
            $currentUser,
            $prepayment,
            $allocations,
            $discountShares,
            $profileMatches,
            $chargeAmount,
            $paymentMethod,
            $couponId,
            $cartTotal,
            $discountAmount,
            $perOrderCharges
        ) {
            $currentUserId = $currentUser->id;
            $cityModel = City::find($this->city_id);
            $areaModel = Area::find($this->area_id);

            if ($chargeAmount > 0 && $paymentMethod === OrderPaymentMethod::BALANCE) {
                try {
                    WalletLedger::debit(
                        $currentUser,
                        $chargeAmount,
                        ($prepayment['required'] ?? false) ? 'Order prepayment' : 'Order payment'
                    );
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException($e->getMessage());
                }
            }

            $fullAddress = trim($this->addressLine1).', '.($areaModel?->name ?? '').', '.($cityModel?->name ?? '');
            $createdOrderIds = [];
            $index = 0;
            $firstOrder = null;
            $chargeService = app(ChargeService::class);

            foreach ($activeOrders as $date => $qty) {
                $foodTotal = (int) round(($this->dish['price'] ?? 0) * $qty);
                $feeLines = $perOrderCharges[(string) $date] ?? [];
                $feesTotal = (int) collect($feeLines)->sum('amount');
                $lineTotal = $foodTotal + $feesTotal;
                $amountPaid = (int) ($allocations[$index] ?? 0);
                $lineDiscount = (int) ($discountShares[$index] ?? 0);
                $index++;

                $order = Order::create([
                    'user_id' => $currentUserId,
                    'menu_item_id' => $this->dish['id'],
                    'quantity' => $qty,
                    'delivery_date' => $date,
                    'delivery_time' => $this->deliveryWindow,
                    'total_amount' => $lineTotal,
                    'charges_amount' => $feesTotal,
                    'amount_paid' => $amountPaid,
                    'prepaid_amount' => $amountPaid,
                    'cash_collected' => 0,
                    'discount_amount' => $lineDiscount,
                    'coupon_id' => $couponId,
                    'address' => $fullAddress,
                    'receiver_name' => $this->customerName,
                    'receiver_mobile' => $this->mobile,
                    'area_id' => $this->area_id,
                    'order_status' => 'pending',
                    'payment_status' => $amountPaid >= max(0, $lineTotal - $lineDiscount) && $lineTotal > 0 ? 'paid' : 'pending',
                    'payment_method' => $paymentMethod,
                    'created_by' => $currentUserId,
                    'updated_by' => $currentUserId,
                ]);
                $chargeService->attachToOrder($order, $feeLines);
                $createdOrderIds[] = $order->id;
                $firstOrder ??= $order;
            }

            if ($couponId && $discountAmount > 0 && $firstOrder) {
                app(CouponService::class)->redeem(
                    \App\Models\Coupon::query()->findOrFail($couponId),
                    $currentUser,
                    CouponRedemption::CONTEXT_ORDER,
                    $cartTotal,
                    $discountAmount,
                    $firstOrder,
                    null,
                    ['order_ids' => $createdOrderIds]
                );
            }

            $grouper = app(MealOrderGrouper::class);
            foreach (Order::query()->whereIn('id', $createdOrderIds)->get() as $order) {
                $grouper->assignOrder($order->load('user'), $currentUserId);
            }

            $profileUpdate = [
                'address' => $this->addressLine1,
                'city_id' => $this->city_id,
                'area_id' => $this->area_id,
                'is_mobile_verified' => true,
            ];
            if ($profileMatches) {
                $profileUpdate['mobile'] = $this->mobile;
            }
            $currentUser->update($profileUpdate);
        });

        $this->showModal = false;
        session()->flash('message', 'Your meal track has been scheduled successfully!');

        return redirect()->to(route('corporates.dashboard'));
    }

    public function getCutoffFormattedProperty(): string
    {
        return OrderCutoff::label();
    }

    /**
     * @param  array<string, mixed>  $prepayment
     */
    protected function resolveCheckoutPaymentMethod(array $prepayment, int $activeDateCount): ?string
    {
        try {
            return OrderPaymentMethod::resolveCheckout(
                $this->paymentMethod,
                (bool) ($prepayment['required'] ?? false),
                $activeDateCount,
                $this->walletBalance(),
                OrderPaymentMethod::checkoutChargeAmount(
                    OrderPaymentMethod::BALANCE,
                    (bool) ($prepayment['required'] ?? false),
                    (int) ($prepayment['amount'] ?? 0),
                    (int) round($this->total)
                )
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('paymentMethod', $prepayment['message'] ?? $e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $prepayment
     */
    protected function checkoutChargeAmount(array $prepayment, int $cartTotal): int
    {
        return OrderPaymentMethod::checkoutChargeAmount(
            $this->paymentMethod,
            (bool) ($prepayment['required'] ?? false),
            (int) ($prepayment['amount'] ?? 0),
            $cartTotal
        );
    }

    public function render()
    {
        return view('livewire.public.order-checkout-modal');
    }
}
