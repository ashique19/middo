<?php

namespace App\Livewire\Shared;

use App\Models\MealItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class MealItemEditModal extends Component
{
    public bool $showModal = false;

    public bool $readOnly = false;

    public ?int $mealItemId = null;

    public string $name = '';

    public ?string $summary = null;

    public int $other_costs = 0;

    public int $recipe_ingredient_cost = 0;

    public int $total_cost = 0;

    public ?string $note = null;

    public $thumbnail;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'other_costs' => 'integer|min:0',
            'note' => 'nullable|string',
            'thumbnail' => 'nullable',
        ];
    }

    #[On('editMealItem')]
    public function loadMealItem($id = null): void
    {
        $mealId = is_array($id) ? ($id['id'] ?? null) : $id;
        if (! $mealId) {
            return;
        }

        $item = MealItem::findOrFail((int) $mealId);
        $this->mealItemId = $item->id;
        $this->name = $item->name;
        $this->summary = $item->summary;
        $this->other_costs = (int) $item->other_costs;
        $this->recipe_ingredient_cost = (int) $item->recipe_ingredient_cost;
        $this->total_cost = (int) $item->total_cost;
        $this->note = $item->note;
        $this->thumbnail = $item->thumbnail ? asset($item->thumbnail) : null;
        $this->readOnly = Auth::user()?->role?->name !== 'admin';
        $this->showModal = true;
    }

    public function update(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);
        $this->validate();

        $item = MealItem::findOrFail($this->mealItemId);
        $item->update([
            'name' => $this->name,
            'summary' => $this->summary,
            'other_costs' => $this->other_costs,
            'note' => $this->note,
        ]);

        if (is_string($this->thumbnail) && str_starts_with($this->thumbnail, 'data:image')) {
            $directory = public_path('img/meal-items');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $filename = "meal-{$item->id}.jpg";
            [, $encoded] = explode(',', $this->thumbnail, 2);
            file_put_contents("$directory/$filename", base64_decode($encoded));
            $item->update(['thumbnail' => 'img/meal-items/'.$filename]);
        }

        $item->recalculateCosts();
        $this->showModal = false;
        $this->dispatch('meal-items-updated');
        $this->dispatch('menu-updated');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.shared.meal-items.edit-modal');
    }
}
