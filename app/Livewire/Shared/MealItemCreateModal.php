<?php

namespace App\Livewire\Shared;

use App\Models\MealItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MealItemCreateModal extends Component
{
    public bool $showModal = false;

    public string $name = '';

    public ?string $summary = null;

    public int $other_costs = 0;

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

    public function open(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);
        $this->reset(['name', 'summary', 'other_costs', 'note', 'thumbnail']);
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);
        $this->validate();

        $item = MealItem::create([
            'name' => $this->name,
            'summary' => $this->summary,
            'other_costs' => $this->other_costs,
            'note' => $this->note,
            'recipe_ingredient_cost' => 0,
            'total_cost' => $this->other_costs,
        ]);

        if (is_string($this->thumbnail) && str_starts_with($this->thumbnail, 'data:image')) {
            $this->storeThumbnail($item);
        }

        $this->showModal = false;
        $this->dispatch('meal-items-updated');
    }

    protected function storeThumbnail(MealItem $item): void
    {
        $directory = public_path('img/meal-items');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = "meal-{$item->id}.jpg";
        [, $encoded] = explode(',', $this->thumbnail, 2);
        file_put_contents("$directory/$filename", base64_decode($encoded));
        $item->update(['thumbnail' => 'img/meal-items/'.$filename]);
    }

    public function render()
    {
        return view('livewire.shared.meal-items.create-modal');
    }
}
