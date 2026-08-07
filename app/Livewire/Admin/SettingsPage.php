<?php

namespace App\Livewire\Admin;

use App\Support\DeliveryRunType;
use App\Support\KitchenTier;
use App\Support\MiddoSettings;
use Livewire\Component;

class SettingsPage extends Component
{
    public int $accept_window_minutes = 120;

    public int $accept_window_warn_minutes = 15;

    public string $accept_window_starts_at = '10:00';

    public string $order_cutoff_time = '15:28';

    public int $auto_group_quantity = 10;

    public int $tier_silver = 1;

    public int $tier_gold = 2;

    public int $tier_platinum = 3;

    public int $commission_corporate_to_kitchen = 30;

    public int $commission_kitchen_to_ops = 25;

    public int $commission_ops_to_kitchen = 25;

    public int $commission_custom = 40;

    public int $commission_mid_run_rescue = 0;

    public bool $kitchen_to_ops_via_rider = false;

    public float $vat_rate_pct = 5;

    public float $eps_fee_bank = 1.5;

    public float $eps_fee_bkash = 1.8;

    public float $eps_fee_nagad = 1.8;

    public float $eps_fee_rocket = 1.8;

    public float $eps_fee_card = 2.5;

    public float $eps_fee_other = 1.5;

    public int $full_prepay_from_active_orders = 3;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $this->loadFromSettings();
    }

    public function loadFromSettings(): void
    {
        $this->accept_window_minutes = MiddoSettings::acceptWindowMinutes();
        $this->accept_window_warn_minutes = MiddoSettings::acceptWindowWarnMinutes();
        $this->accept_window_starts_at = MiddoSettings::acceptWindowStartsAt() ?? '';
        $this->order_cutoff_time = MiddoSettings::orderCutoffTime();
        $this->auto_group_quantity = MiddoSettings::autoGroupQuantity();
        $defaults = MiddoSettings::tierDefaults();
        $this->tier_silver = $defaults[KitchenTier::SILVER];
        $this->tier_gold = $defaults[KitchenTier::GOLD];
        $this->tier_platinum = $defaults[KitchenTier::PLATINUM];

        $commissions = MiddoSettings::deliveryCommissionDefaults();
        $this->commission_corporate_to_kitchen = $commissions[DeliveryRunType::CORPORATE_TO_KITCHEN];
        $this->commission_kitchen_to_ops = $commissions[DeliveryRunType::KITCHEN_TO_OPS];
        $this->commission_ops_to_kitchen = $commissions[DeliveryRunType::OPS_TO_KITCHEN];
        $this->commission_custom = $commissions[DeliveryRunType::CUSTOM];
        $this->commission_mid_run_rescue = MiddoSettings::midRunRescueCommission();
        $this->kitchen_to_ops_via_rider = MiddoSettings::kitchenToOpsViaRider();
        $this->vat_rate_pct = MiddoSettings::vatRatePct();
        $this->full_prepay_from_active_orders = MiddoSettings::fullPrepayFromActiveOrders();

        $fees = MiddoSettings::epsFeeRates();
        $this->eps_fee_bank = $fees['bank'];
        $this->eps_fee_bkash = $fees['bkash'];
        $this->eps_fee_nagad = $fees['nagad'];
        $this->eps_fee_rocket = $fees['rocket'];
        $this->eps_fee_card = $fees['card'];
        $this->eps_fee_other = $fees['other'];
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'accept_window_minutes' => 'required|integer|min:1|max:10080',
            'accept_window_warn_minutes' => 'required|integer|min:1|max:10080',
            'accept_window_starts_at' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value === null || trim((string) $value) === '') {
                    return;
                }
                try {
                    \Carbon\Carbon::parse($value, 'Asia/Dhaka');
                } catch (\Throwable) {
                    $fail('Enter a valid start time (e.g. 10:00).');
                }
            }],
            'order_cutoff_time' => ['required', 'string', 'max:20', function ($attribute, $value, $fail) {
                try {
                    \Carbon\Carbon::parse($value, 'Asia/Dhaka');
                } catch (\Throwable) {
                    $fail('Enter a valid cutoff time (e.g. 15:28).');
                }
            }],
            'auto_group_quantity' => 'required|integer|min:1|max:500',
            'tier_silver' => 'required|integer|min:0|max:100',
            'tier_gold' => 'required|integer|min:0|max:100',
            'tier_platinum' => 'required|integer|min:0|max:100',
            'commission_corporate_to_kitchen' => 'required|integer|min:0|max:100000',
            'commission_kitchen_to_ops' => 'required|integer|min:0|max:100000',
            'commission_ops_to_kitchen' => 'required|integer|min:0|max:100000',
            'commission_custom' => 'required|integer|min:0|max:100000',
            'commission_mid_run_rescue' => 'required|integer|min:0|max:100000',
            'kitchen_to_ops_via_rider' => 'boolean',
            'vat_rate_pct' => 'required|numeric|min:0|max:100',
            'eps_fee_bank' => 'required|numeric|min:0|max:100',
            'eps_fee_bkash' => 'required|numeric|min:0|max:100',
            'eps_fee_nagad' => 'required|numeric|min:0|max:100',
            'eps_fee_rocket' => 'required|numeric|min:0|max:100',
            'eps_fee_card' => 'required|numeric|min:0|max:100',
            'eps_fee_other' => 'required|numeric|min:0|max:100',
            'full_prepay_from_active_orders' => 'required|integer|min:1|max:100',
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => $this->accept_window_minutes,
            'accept_window_warn_minutes' => $this->accept_window_warn_minutes,
            'accept_window_starts_at' => $this->accept_window_starts_at,
            'order_cutoff_time' => $this->order_cutoff_time,
            'auto_group_quantity' => $this->auto_group_quantity,
            'tier_defaults' => [
                KitchenTier::SILVER => $this->tier_silver,
                KitchenTier::GOLD => $this->tier_gold,
                KitchenTier::PLATINUM => $this->tier_platinum,
            ],
            'delivery_commissions' => [
                DeliveryRunType::CORPORATE_TO_KITCHEN => $this->commission_corporate_to_kitchen,
                DeliveryRunType::KITCHEN_TO_OPS => $this->commission_kitchen_to_ops,
                DeliveryRunType::OPS_TO_KITCHEN => $this->commission_ops_to_kitchen,
                DeliveryRunType::CUSTOM => $this->commission_custom,
            ],
            'mid_run_rescue_commission' => $this->commission_mid_run_rescue,
            'kitchen_to_ops_via_rider' => $this->kitchen_to_ops_via_rider,
            'vat_rate_pct' => $this->vat_rate_pct,
            'full_prepay_from_active_orders' => $this->full_prepay_from_active_orders,
            'eps_fee_rates' => [
                'bank' => $this->eps_fee_bank,
                'bkash' => $this->eps_fee_bkash,
                'nagad' => $this->eps_fee_nagad,
                'rocket' => $this->eps_fee_rocket,
                'card' => $this->eps_fee_card,
                'other' => $this->eps_fee_other,
            ],
        ]);

        $this->loadFromSettings();
        $this->statusMessage = 'Settings saved. Existing kitchens keep their own allowed quantity until reset or re-activated with a null override.';
    }

    public function render()
    {
        return view('livewire.admin.settings-page')
            ->layout('layouts.private.app', ['title' => 'Settings']);
    }
}
