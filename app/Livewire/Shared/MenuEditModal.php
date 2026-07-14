<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class MenuEditModal extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    public ?int $menuItemId = null;

    public string $name = '';

    public ?string $summary = null;

    public int $price = 0;

    public float $kitchen_commission_percentage = 0.0;

    public int $kitchen_commission = 0;

    public int $other_cost = 0;

    public int $meals_cost = 0;

    public ?string $note = null;

    public $thumbnail;

    public int $display_order = 0;

    public bool $is_featured = false;

    public bool $is_homepage = false;

    public bool $readOnly = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'other_cost' => 'integer|min:0',
            'note' => 'nullable|string',
            'display_order' => 'integer',
            'thumbnail' => 'nullable',
        ];
    }

    #[On('editMenuItem')]
    public function loadMenuItem($id = null): void
    {
        $menuId = is_array($id) ? ($id['id'] ?? null) : $id;
        if (! $menuId) {
            return;
        }

        $item = MenuItem::findOrFail((int) $menuId);
        $this->menuItemId = $item->id;
        $this->name = $item->name;
        $this->summary = $item->summary;
        $this->price = (int) $item->price;
        $this->kitchen_commission = (int) $item->kitchen_commission;
        $this->other_cost = (int) $item->other_cost;
        $this->meals_cost = (int) $item->meals_cost;
        $this->note = $item->note;
        $this->display_order = $item->display_order;
        $this->is_featured = $item->is_featured;
        $this->is_homepage = $item->is_homepage;
        $this->kitchen_commission_percentage = $this->price > 0
            ? round(($this->kitchen_commission / $this->price) * 100, 2)
            : 0.0;
        $this->thumbnail = $item->thumbnail ? asset($item->thumbnail) : null;
        $this->readOnly = Auth::user()?->role?->name !== 'admin';
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

    public function update(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);
        $this->validate();
        $this->calculateKitchenCommission();

        $item = MenuItem::findOrFail($this->menuItemId);
        $item->update([
            'name' => $this->name,
            'summary' => $this->summary,
            'price' => $this->price,
            'kitchen_commission' => $this->kitchen_commission,
            'other_cost' => $this->other_cost,
            'note' => $this->note,
            'is_featured' => $this->is_featured,
            'is_homepage' => $this->is_homepage,
            'display_order' => $this->display_order,
        ]);

        if ($this->thumbnail && is_string($this->thumbnail) && str_starts_with($this->thumbnail, 'data:image')) {
            $this->processThumbnail($item);
        }

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
        [, $encoded] = explode(',', $this->thumbnail, 2);
        file_put_contents("$directory/$filename", base64_decode($encoded));
        $item->update(['thumbnail' => 'img/menu/'.$filename]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.shared.menu.edit-modal');
    }
}
