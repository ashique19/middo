<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\MenuItem;
use App\Models\City;   
use App\Models\Area;   
use Livewire\Attributes\On;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
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
        
        if ($this->city_id) {
            $this->loadAreasForSelectedCity($this->city_id);
        }
        
        $this->recalculateTotals();
        
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

        $this->quantities[$date] = max(1, min(5, $this->quantities[$date] + $amount));
        $this->recalculateTotals();
    }

    /**
     * Step 1: Validates entries against strict local numbers and fires verification token.
     */
    public function initiateOrderConfirmation()
    {
        if (!Auth::check()) return redirect()->guest(route('login'));

        $this->validate([
            'addressLine1' => 'required|string|min:5|max:255',
            'city_id'      => 'required|exists:cities,id',
            'area_id'      => 'required|exists:areas,id',
            'mobile'       => 'required|string|regex:/^01[3-9]\d{8}$/', 
            'quantities'   => 'required|array',
            'quantities.*' => 'required|integer|min:0|max:5',
        ], [
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

        $formattedMobile = '88' . trim($this->mobile);
        $otpCode = "1234"; //(string) random_int(1000, 9999);
        Cache::put('order_confirmation_otp_' . $formattedMobile, $otpCode, 300);

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post(config('services.mimsms.base_url'), [
                    'apiKey'          => config('services.mimsms.api_key'),
                    'userName'        => config('services.mimsms.user_name'),
                    'campaignName'    => 'null',
                    'senderName'      => config('services.mimsms.sender_name'),
                    'transactionType' => 'T', 
                    'mobileNumber'    => $formattedMobile,
                    'message'         => "Your Middo Order Confirmation Code is: {$otpCode}. Enter this code to finalize your schedule.",
                ]);

            if ($response->successful()) {
                $this->isConfirmingOtp = true;
                session()->flash('order_status', 'Confirmation code sent successfully.');
            } else {
                $this->addError('mobile', 'SMS channel transmission error. Please retry.');
            }
        } catch (\Exception $e) {
            $this->addError('mobile', 'Connection problem with SMS provider.');
        }
    }

    /**
     * Step 2: Finalizes verification token confirmation and creates individual orders.
     */
    public function finalizeOrder()
    {
        $this->validate(['otpInput' => 'required|string|size:4']);

        $formattedMobile = '88' . trim($this->mobile);
        $cachedOtp = Cache::get('order_confirmation_otp_' . $formattedMobile);

        if (!$cachedOtp || $this->otpInput !== $cachedOtp) {
            $this->addError('otpInput', 'Invalid or expired confirmation token code.');
            return;
        }

        Cache::forget('order_confirmation_otp_' . $formattedMobile);
        $activeOrders = array_filter($this->quantities, fn($qty) => $qty > 0);

        if (empty($activeOrders)) {
            $this->addError('quantities', 'Please select a quantity for at least one delivery date.');
            return;
        }

        DB::transaction(function () use ($activeOrders) {
            $currentUserId = Auth::id();
            $cityModel = City::find($this->city_id);
            $areaModel = Area::find($this->area_id);
            $currentUser = Auth::user();
            
            $fullAddress = trim($this->addressLine1) . ', ' . ($areaModel?->name ?? '') . ', ' . ($cityModel?->name ?? '');
            
            foreach ($activeOrders as $date => $qty) {
                $lineTotal = (int) round(($this->dish['price'] ?? 0) * $qty);

                \App\Models\Order::create([
                    'user_id'         => $currentUserId,
                    'menu_item_id'    => $this->dish['id'],
                    'quantity'        => $qty,
                    'delivery_date'   => $date,
                    'delivery_time'   => $this->deliveryWindow,
                    'total_amount'    => $lineTotal,
                    'address'         => $fullAddress,
                    'status'          => 'pending',
                    'created_by'      => $currentUserId,
                    'updated_by'      => $currentUserId,
                ]);
            }
            
            if ($currentUser instanceof User) {
                $currentUser->update([
                    'mobile'             => $this->mobile,
                    'address'            => $this->addressLine1,
                    'city_id'            => $this->city_id,
                    'area_id'            => $this->area_id,
                    'is_mobile_verified' => true,
                ]);
            }
        });

        $this->showModal = false;
        session()->flash('message', 'Your meal track has been scheduled successfully!');
        
        return redirect()->to(route('menu'));
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