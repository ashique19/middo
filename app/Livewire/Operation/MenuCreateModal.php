<?php

namespace App\Livewire\Operation;

use App\Models\MenuItem;
use Livewire\Component;
use Livewire\WithFileUploads;

class MenuCreateModal extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    
    // Loosened type-hint constraints slightly to safely capture temporary null states from empty browser inputs
    public string $name = '';
    public ?string $summary = null;
    public ?float $price = 0.0;
    public ?float $kitchen_commission_percentage = 0.0;
    public ?float $kitchen_commission = 0.0;
    public $thumbnail;
    public int $display_order = 0;
    public bool $is_featured = false;
    public bool $is_homepage = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'kitchen_commission_percentage' => 'required|numeric|min:0|max:100',
            'display_order' => 'integer',
            'thumbnail' => 'nullable',
        ];
    }

    public function updatedPrice($value): void
    {
        if (is_numeric($value)) {
            $this->price = (float) $value;
        }

        $this->calculateKitchenCommission();
    }

    public function updatedKitchenCommissionPercentage($value): void
    {
        if (is_numeric($value)) {
            $this->kitchen_commission_percentage = (float) $value;
        }

        $this->calculateKitchenCommission();
    }

    protected function calculateKitchenCommission(): void
    {
        $price = is_numeric($this->price) ? (float) $this->price : 0.0;
        $percentage = is_numeric($this->kitchen_commission_percentage) ? (float) $this->kitchen_commission_percentage : 0.0;

        $this->kitchen_commission = ($price >= 0 && $percentage >= 0)
            ? round(($price * $percentage) / 100, 2)
            : 0.0;
    }

    public function save(): void
    {
        $this->validate();
        $this->calculateKitchenCommission();

        $item = MenuItem::create([
            'name' => $this->name,
            'summary' => $this->summary,
            'price' => $this->price,
            // FIXED: Added missing database columns
            // 'kitchen_commission_percentage' => $this->kitchen_commission_percentage,
            'kitchen_commission' => $this->kitchen_commission,
            'is_featured' => $this->is_featured,
            'is_homepage' => $this->is_homepage,
            'display_order' => $this->display_order,
        ]);

        if ($this->thumbnail) {
            $this->processThumbnail($item);
        }

        $this->resetForm();
        $this->showModal = false;

        $this->dispatch('menu-updated');
    }

    protected function processThumbnail(MenuItem $item): void
    {
        $directory = public_path('img/menu');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "menu-{$item->id}.jpg";

        if (is_string($this->thumbnail) && str_starts_with($this->thumbnail, 'data:image')) {
            [, $encoded] = explode(',', $this->thumbnail, 2);
            file_put_contents("$directory/$filename", base64_decode($encoded));
        } elseif (is_object($this->thumbnail) && method_exists($this->thumbnail, 'storeAs')) {
            $this->thumbnail->move($directory, $filename);
        }

        $item->update(['thumbnail' => 'img/menu/' . $filename]);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'name',
            'summary',
            'price',
            'kitchen_commission_percentage',
            'kitchen_commission',
            'thumbnail',
            'display_order',
            'is_featured',
            'is_homepage',
        ]);
    }

    public function render()
    {
        return view('livewire.operation.menu.create-modal');
    }
}