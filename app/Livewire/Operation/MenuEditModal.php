<?php

namespace App\Livewire\Operation;

use App\Models\MenuItem;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

class MenuEditModal extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public ?MenuItem $menuItem = null;

    // Form Fields
    public string $name = '';
    public ?string $summary = null;
    public ?float $price = 0.0;
    public ?float $kitchen_commission_percentage = 0.0;
    public ?float $kitchen_commission = 0.0;
    public $thumbnail;
    public int $display_order = 0;
    public bool $is_featured = false;
    public bool $is_homepage = false;

    protected $listeners = ['editMenuItem' => 'loadMenuItem'];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'display_order' => 'integer',
            'thumbnail' => 'nullable',
        ];
    }

    public function loadMenuItem(int $id): void
    {
        $this->menuItem = MenuItem::findOrFail($id);

        // Bind model data to properties
        $this->name = $this->menuItem->name;
        $this->summary = $this->menuItem->summary;
        $this->price = (float) $this->menuItem->price;
        $this->kitchen_commission = (float) $this->menuItem->kitchen_commission;
        $this->display_order = $this->menuItem->display_order;
        $this->is_featured = $this->menuItem->is_featured;
        $this->is_homepage = $this->menuItem->is_homepage;
        
        // Reverse engineer the percentage helper for the frontend UI input
        if ($this->price > 0) {
            $this->kitchen_commission_percentage = round(($this->kitchen_commission / $this->price) * 100, 2);
        } else {
            $this->kitchen_commission_percentage = 0.0;
        }

        // Set thumbnail placeholder path for existing records
        $this->thumbnail = $this->menuItem->thumbnail ? asset($this->menuItem->thumbnail) : null;

        $this->showModal = true;
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

    public function update(): void
    {
        $this->validate();
        $this->calculateKitchenCommission();

        $this->menuItem->update([
            'name' => $this->name,
            'summary' => $this->summary,
            'price' => $this->price,
            'kitchen_commission' => $this->kitchen_commission,
            'is_featured' => $this->is_featured,
            'is_homepage' => $this->is_homepage,
            'display_order' => $this->display_order,
        ]);

        // Process thumbnail updates only if a fresh asset string/object is bound
        if ($this->thumbnail && !str_starts_with($this->thumbnail, asset(''))) {
            $this->processThumbnail($this->menuItem);
        }

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

    public function render()
    {
        return view('livewire.operation.menu.edit-modal');
    }
}