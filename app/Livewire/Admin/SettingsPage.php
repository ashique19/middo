<?php

namespace App\Livewire\Admin;

use App\Support\KitchenTier;
use App\Support\MiddoSettings;
use Livewire\Component;

class SettingsPage extends Component
{
    public int $accept_window_minutes = 120;

    public int $auto_group_quantity = 10;

    public int $tier_silver = 1;

    public int $tier_gold = 2;

    public int $tier_platinum = 3;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $this->loadFromSettings();
    }

    public function loadFromSettings(): void
    {
        $this->accept_window_minutes = MiddoSettings::acceptWindowMinutes();
        $this->auto_group_quantity = MiddoSettings::autoGroupQuantity();
        $defaults = MiddoSettings::tierDefaults();
        $this->tier_silver = $defaults[KitchenTier::SILVER];
        $this->tier_gold = $defaults[KitchenTier::GOLD];
        $this->tier_platinum = $defaults[KitchenTier::PLATINUM];
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'accept_window_minutes' => 'required|integer|min:1|max:10080',
            'auto_group_quantity' => 'required|integer|min:1|max:500',
            'tier_silver' => 'required|integer|min:0|max:100',
            'tier_gold' => 'required|integer|min:0|max:100',
            'tier_platinum' => 'required|integer|min:0|max:100',
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => $this->accept_window_minutes,
            'auto_group_quantity' => $this->auto_group_quantity,
            'tier_defaults' => [
                KitchenTier::SILVER => $this->tier_silver,
                KitchenTier::GOLD => $this->tier_gold,
                KitchenTier::PLATINUM => $this->tier_platinum,
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
