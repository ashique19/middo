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
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'accept_window_minutes' => 'required|integer|min:1|max:10080',
            'accept_window_warn_minutes' => 'required|integer|min:1|max:10080',
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
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => $this->accept_window_minutes,
            'accept_window_warn_minutes' => $this->accept_window_warn_minutes,
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
