<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\MenuItem;
use App\Models\City;   
use App\Models\Area;   
use Livewire\Attributes\On;
use App\Models\User;
use App\Support\CorporateOrderLimit;
use App\Support\CorporateOrderPrepayment;
use App\Support\OrderConfirmationOtp;
use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderCheckoutModal extends Component
{
    public bool $showModal = false;
    public array $dish = [];

    // Cut-off Configuration Scope 
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
    public string $paymentMethod = 'balance';
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

    /**
     * Component boot initialization.
     */
    public function mount(): void
    {
        $this->citiesList = City::all()->toArray();

        if (Auth::check()) {
            $user = Auth::user();
            $this->customerName = $user->name ?? 'User';
            $this->addressLine1 = $user->address ?? '';
            $this->city_id = $user->city_id ?? (!empty($this->citiesList) ? $this->citiesList[0]['id'] : null);
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
        if (!$id) return;

        if (!Auth::check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        // Re-load basic arrays to guarantee fresh data structures
        $this->citiesList = City::all()->toArray();
        $user = Auth::user();
        $this->customerName = $user->name ?? 'User';
        $this->dish = MenuItem::findOrFail($id)->toArray();
        
        $dhakaNow = now()->setTimezone('Asia/Dhaka');
        $cutoffTime = now()->setTimezone('Asia/Dhaka')->setTime($this->cutoffHour, $this->cutoffMinute, 0);
        $this->isPastCutoff = $dhakaNow->greaterThanOrEqualTo($cutoffTime);

        $this->availableDates = [];
        $this->quantities = [];
        $startOffset = $this->isPastCutoff ? 1 : 0;
        
        for ($i = $startOffset; $i < ($startOffset + 9); $i++) {
            $dateString = now()->setTimezone('Asia/Dhaka')->addDays($i)->format('Y-m-d');
            $this->availableDates[] = $dateString;
            $this->quantities[$dateString] = ($i === $startOffset) ? 1 : 0;
        }

        $this->selectedDate = $this->availableDates[0] ?? now()->setTimezone('Asia/Dhaka')->format('Y-m-d');
        
        $this->addressLine1 = $user->address ?? '';
        $this->city_id = $user->city_id ?? (!empty($this->citiesList) ? $this->citiesList[0]['id'] : null);
        $this->area_id = $user->area_id;
        $this->mobile = $user->mobile ?? '';
        
        $this->isConfirmingOtp = false;
        $this->otpInput = '';
        $this->paymentMethod = 'balance';
        $this->prepayment = [];
        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;
        
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
    }

    /**
     * Queries relation context directly via City model mapping.
     */
    protected function loadAreasForSelectedCity($cityId): void
    {
        $selectedCity = City::find($cityId);
        $this->areasList = $selectedCity ? $selectedCity->areas->toArray() : [];
        
        // Reset or default area choice safely if current selection falls out of scope
        if (!empty($this->areasList)) {
            $validIds = array_column($this->areasList, 'id');
            if (!in_array($this->area_id, $validIds)) {
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
        if (!$this->dish) return;

        $totalItemsCount = array_sum($this->quantities);
        $this->subtotal = (float) ($this->dish['price'] ?? 0) * $totalItemsCount;
        $this->total = $this->subtotal + $this->taxesAndFees;
        $this->refreshPrepaymentQuote();
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

        $this->prepayment = CorporateOrderPrepayment::evaluate(
            $user,
            $this->customerName,
            $this->mobile !== '' ? $this->mobile : (string) $user->mobile,
            count($activeDates),
            $cartTotal
        );
    }

    /**
     * Toggles a date between active (qty 1) and inactive (qty 0)
     */
    public function toggleDateSelection(string $dateString): void
    {
        if ($this->isConfirmingOtp) return; 
        
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
        if ($this->isConfirmingOtp || !isset($this->quantities[$date]) || $this->quantities[$date] === 0) return;

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
        if (!Auth::check()) return redirect()->guest(route('login'));

        $this->validate([
            'customerName' => 'required|string|min:2|max:120',
            'addressLine1' => 'required|string|min:5|max:255',
            'city_id'      => 'required|exists:cities,id',
            'area_id'      => 'required|exists:areas,id',
            'mobile'       => 'required|string|regex:/^01[3-9]\d{8}$/', 
            'quantities'   => 'required|array',
            'quantities.*' => 'required|integer|min:0|max:'.CorporateOrderLimit::maxAllowed(),
        ], [
            'customerName.required' => 'Please provide the receiver name.',
            'addressLine1.required' => 'Please provide your specific street address details.',
            'city_id.required'      => 'Please select a delivery city.',
            'area_id.required'      => 'Please select an area location.',
            'mobile.required'       => 'Mobile number is required to receive confirmation OTP.',
            'mobile.regex'          => 'Provide a valid 11-digit mobile number format (e.g., 01710123456).',
        ]);

        $activeOrders = array_filter($this->quantities, fn($qty) => $qty > 0);
        if (empty($activeOrders)) {
            $this->addError('quantities', 'Please select a quantity for at least one delivery date.');
            return;
        }

        if (! $this->validateDailyQuantities($activeOrders)) {
            return;
        }

        $this->refreshPrepaymentQuote();

        if (($this->prepayment['required'] ?? false) && $this->paymentMethod === 'balance' && ! ($this->prepayment['balance_sufficient'] ?? false)) {
            $this->addError('paymentMethod', $this->prepayment['message'] ?? 'Insufficient Middo Balance for required prepayment. Choose online pay or top up.');
            return;
        }

        $this->gatewayPaymentToken = null;
        $this->gatewayPaymentUrl = null;

        if (($this->prepayment['required'] ?? false) && $this->paymentMethod === 'gateway') {
            $fingerprint = [
                'menu_item_id' => (int) ($this->dish['id'] ?? 0),
                'receiver_name' => CorporateOrderPrepayment::normalizeName($this->customerName),
                'mobile' => CorporateOrderPrepayment::normalizeMobile($this->mobile),
                'dates' => collect($activeOrders)->map(fn ($qty, $date) => [
                    'date' => $date,
                    'quantity' => (int) $qty,
                ])->values()->all(),
                'amount' => (int) $this->prepayment['amount'],
            ];

            $checkout = app(PaymentGateway::class)->createCheckout(
                (int) Auth::id(),
                (int) $this->prepayment['amount'],
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
            'paymentMethod' => 'nullable|in:balance,gateway',
        ]);

        if (! OrderConfirmationOtp::verify($this->mobile, $this->otpInput)) {
            $this->addError('otpInput', 'Invalid or expired confirmation token code.');

            return;
        }

        $activeOrders = array_filter($this->quantities, fn($qty) => $qty > 0);

        if (empty($activeOrders)) {
            $this->addError('quantities', 'Please select a quantity for at least one delivery date.');
            return;
        }

        if (! $this->validateDailyQuantities($activeOrders)) {
            return;
        }

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $this->refreshPrepaymentQuote();
        $prepayment = $this->prepayment;

        if ($prepayment['required'] ?? false) {
            if (! in_array($this->paymentMethod, ['balance', 'gateway'], true)) {
                $this->addError('paymentMethod', $prepayment['message'] ?? 'Prepayment is required.');
                return;
            }

            if ($this->paymentMethod === 'balance' && ! ($prepayment['balance_sufficient'] ?? false)) {
                $this->addError('paymentMethod', 'Insufficient Middo Balance for required prepayment.');
                return;
            }

            if ($this->paymentMethod === 'gateway') {
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
        foreach ($activeOrders as $qty) {
            $lineTotals[] = (int) round(($this->dish['price'] ?? 0) * (int) $qty);
        }
        $allocations = CorporateOrderPrepayment::allocate(
            ($prepayment['required'] ?? false) ? (int) $prepayment['amount'] : 0,
            $lineTotals
        );
        $profileMatches = CorporateOrderPrepayment::profileMatchesReceiver(
            $currentUser,
            $this->customerName,
            $this->mobile
        );

        DB::transaction(function () use ($activeOrders, $currentUser, $prepayment, $allocations, $profileMatches) {
            $currentUserId = $currentUser->id;
            $cityModel = City::find($this->city_id);
            $areaModel = Area::find($this->area_id);

            if (($prepayment['required'] ?? false) && $this->paymentMethod === 'balance' && ($prepayment['amount'] ?? 0) > 0) {
                $locked = User::query()->whereKey($currentUserId)->lockForUpdate()->firstOrFail();
                if ((int) $locked->balance < (int) $prepayment['amount']) {
                    throw new \RuntimeException('Insufficient Middo Balance for required prepayment.');
                }
                $locked->decrement('balance', (int) $prepayment['amount']);
            }
            
            $fullAddress = trim($this->addressLine1) . ', ' . ($areaModel?->name ?? '') . ', ' . ($cityModel?->name ?? '');
            $createdOrderIds = [];
            $index = 0;

            foreach ($activeOrders as $date => $qty) {
                $lineTotal = (int) round(($this->dish['price'] ?? 0) * $qty);
                $amountPaid = (int) ($allocations[$index] ?? 0);
                $index++;

                $order = \App\Models\Order::create([
                    'user_id'         => $currentUserId,
                    'menu_item_id'    => $this->dish['id'],
                    'quantity'        => $qty,
                    'delivery_date'   => $date,
                    'delivery_time'   => $this->deliveryWindow,
                    'total_amount'    => $lineTotal,
                    'amount_paid'     => $amountPaid,
                    'prepaid_amount'  => $amountPaid,
                    'cash_collected'  => 0,
                    'address'         => $fullAddress,
                    'receiver_name'   => $this->customerName,
                    'receiver_mobile' => $this->mobile,
                    'area_id'         => $this->area_id,
                    'order_status'    => 'pending',
                    'payment_status'  => $amountPaid >= $lineTotal && $lineTotal > 0 ? 'paid' : 'pending',
                    'created_by'      => $currentUserId,
                    'updated_by'      => $currentUserId,
                ]);
                $createdOrderIds[] = $order->id;
            }

            $grouper = app(\App\Support\MealOrderGrouper::class);
            foreach (\App\Models\Order::query()->whereIn('id', $createdOrderIds)->get() as $order) {
                $grouper->assignOrder($order->load('user'), $currentUserId);
            }
            
            $profileUpdate = [
                'address'            => $this->addressLine1,
                'city_id'            => $this->city_id,
                'area_id'            => $this->area_id,
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
        return Carbon::createFromTime($this->cutoffHour, $this->cutoffMinute)->format('g:i A');
    }

    public function render()
    {
        return view('livewire.public.order-checkout-modal');
    }
}