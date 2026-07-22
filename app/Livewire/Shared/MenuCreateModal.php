<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class MenuCreateModal extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    public string $name = '';

    public ?string $summary = null;

    public int $price = 0;

    public float $kitchen_commission_percentage = 0.0;

    public int $kitchen_commission = 0;

    public int $delivery_commission = 0;

    public int $other_cost = 0;

    public ?string $note = null;

    public $thumbnail;

    public int $display_order = 0;

    public bool $is_featured = false;

    public bool $is_homepage = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'kitchen_commission_percentage' => 'required|numeric|min:0|max:100',
            'delivery_commission' => 'integer|min:0',
            'other_cost' => 'integer|min:0',
            'note' => 'nullable|string',
            'display_order' => 'integer',
            'thumbnail' => 'nullable',
        ];
    }

    public function open(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showModal = true;
    }

    public function updatedPrice($value): void
    {
        $this->price = is_numeric($value) ? (int) $value : 0;
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
        $this->kitchen_commission = (int) round(($this->price * $this->kitchen_commission_percentage) / 100);
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->validate();
        $this->calculateKitchenCommission();

        $item = MenuItem::create([
            'name' => $this->name,
            'summary' => $this->summary,
            'price' => $this->price,
            'kitchen_commission' => $this->kitchen_commission,
            'delivery_commission' => $this->delivery_commission,
            'other_cost' => $this->other_cost,
            'note' => $this->note,
            'is_featured' => $this->is_featured,
            'is_homepage' => $this->is_homepage,
            'display_order' => $this->display_order,
            'meals_cost' => 0,
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
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "menu-{$item->id}.jpg";

        if (is_string($this->thumbnail) && str_starts_with($this->thumbnail, 'data:image')) {
            [, $encoded] = explode(',', $this->thumbnail, 2);
            file_put_contents("$directory/$filename", base64_decode($encoded));
        }

        $item->update(['thumbnail' => 'img/menu/'.$filename]);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'name', 'summary', 'price', 'kitchen_commission_percentage', 'kitchen_commission',
            'other_cost', 'note', 'thumbnail', 'display_order', 'is_featured', 'is_homepage',
        ]);
    }

    protected function authorizeManage(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);
    }

    public function render()
    {
        return view('livewire.shared.menu.create-modal');
    }
}
