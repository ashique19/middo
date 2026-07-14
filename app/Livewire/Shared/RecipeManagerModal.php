<?php

namespace App\Livewire\Shared;

use App\Models\MealItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipePhoto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class RecipeManagerModal extends Component
{
    public bool $showModal = false;

    public bool $canManage = false;

    public ?int $mealItemId = null;

    public string $mealName = '';

    public array $recipes = [];

    public bool $editing = false;

    public ?int $recipeId = null;

    public string $title = '';

    public ?string $instructions = null;

    public ?string $training_video_url = null;

    public bool $is_active = false;

    /** @var array<int, array{name:string,quantity:string,unit:string,cost:int}> */
    public array $ingredients = [];

    /** @var array<int, string> Existing photo paths */
    public array $existingPhotos = [];

    /** @var array<int, string> New base64 photos */
    public array $newPhotos = [];

    #[On('open-recipe-manager')]
    public function open($mealItemId = null): void
    {
        $id = is_array($mealItemId) ? ($mealItemId['mealItemId'] ?? null) : $mealItemId;
        if (! $id) {
            return;
        }

        $meal = MealItem::with('activeRecipe')->findOrFail((int) $id);
        $this->canManage = Auth::user()?->role?->name === 'admin';
        $this->mealItemId = $meal->id;
        $this->mealName = $meal->name;
        $this->editing = false;
        $this->loadRecipes();
        $this->showModal = true;

        // Operation: open the active recipe detail immediately
        if (! $this->canManage) {
            $activeId = $meal->activeRecipe?->id
                ?? data_get(collect($this->recipes)->firstWhere('is_active', true), 'id')
                ?? data_get(collect($this->recipes)->first(), 'id');

            if ($activeId) {
                $this->startEdit((int) $activeId);
            }
        }
    }

    public function startCreate(): void
    {
        abort_unless($this->canManage, 403);
        $this->editing = true;
        $this->recipeId = null;
        $this->title = '';
        $this->instructions = null;
        $this->training_video_url = null;
        $this->is_active = count($this->recipes) === 0;
        $this->ingredients = [
            ['name' => '', 'quantity' => '1', 'unit' => 'pcs', 'cost' => 0],
        ];
        $this->existingPhotos = [];
        $this->newPhotos = [];
    }

    public function startEdit(int $recipeId): void
    {
        $recipe = Recipe::with(['ingredients', 'photos'])->findOrFail($recipeId);
        abort_unless($recipe->meal_item_id === $this->mealItemId, 404);

        $this->editing = true;
        $this->recipeId = $recipe->id;
        $this->title = $recipe->title;
        $this->instructions = $recipe->instructions;
        $this->training_video_url = $recipe->training_video_url;
        $this->is_active = $recipe->is_active;
        $mappedIngredients = $recipe->ingredients->map(fn (RecipeIngredient $ing) => [
            'name' => $ing->name,
            'quantity' => (string) $ing->quantity,
            'unit' => $ing->unit ?? '',
            'cost' => (int) $ing->cost,
        ])->all();

        $this->ingredients = $mappedIngredients ?: (
            $this->canManage
                ? [['name' => '', 'quantity' => '1', 'unit' => 'pcs', 'cost' => 0]]
                : []
        );
        $this->existingPhotos = $recipe->photos->pluck('path')->all();
        $this->newPhotos = [];
    }

    public function addIngredientRow(): void
    {
        abort_unless($this->canManage, 403);
        $this->ingredients[] = ['name' => '', 'quantity' => '1', 'unit' => 'pcs', 'cost' => 0];
    }

    public function removeIngredientRow(int $index): void
    {
        abort_unless($this->canManage, 403);
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function removeExistingPhoto(int $index): void
    {
        abort_unless($this->canManage, 403);
        unset($this->existingPhotos[$index]);
        $this->existingPhotos = array_values($this->existingPhotos);
    }

    public function addPhotoDataUrl(string $dataUrl): void
    {
        abort_unless($this->canManage, 403);
        $this->newPhotos[] = $dataUrl;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->loadRecipes();
    }

    public function saveRecipe(): void
    {
        abort_unless($this->canManage && $this->mealItemId, 403);

        if ($this->training_video_url === '') {
            $this->training_video_url = null;
        }

        $this->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'training_video_url' => 'nullable|url|max:500',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => 'required|string|max:255',
            'ingredients.*.quantity' => 'required|numeric|min:0',
            'ingredients.*.unit' => 'nullable|string|max:50',
            'ingredients.*.cost' => 'required|integer|min:0',
        ]);

        DB::transaction(function () {
            if ($this->recipeId) {
                $recipe = Recipe::where('meal_item_id', $this->mealItemId)->findOrFail($this->recipeId);
                $recipe->update([
                    'title' => $this->title,
                    'instructions' => $this->instructions,
                    'training_video_url' => $this->training_video_url,
                ]);
            } else {
                $recipe = Recipe::create([
                    'meal_item_id' => $this->mealItemId,
                    'title' => $this->title,
                    'instructions' => $this->instructions,
                    'training_video_url' => $this->training_video_url,
                    'is_active' => false,
                ]);
            }

            $recipe->ingredients()->delete();
            foreach ($this->ingredients as $index => $row) {
                $recipe->ingredients()->create([
                    'name' => $row['name'],
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'] ?: null,
                    'cost' => (int) $row['cost'],
                    'sort_order' => $index + 1,
                ]);
            }

            $kept = $this->existingPhotos;
            $recipe->photos()->whereNotIn('path', $kept ?: [''])->get()->each(function (RecipePhoto $photo) {
                if (file_exists(public_path($photo->path))) {
                    @unlink(public_path($photo->path));
                }
                $photo->delete();
            });

            $directory = public_path('img/recipes');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $sort = count($kept);
            foreach ($this->newPhotos as $dataUrl) {
                if (! str_starts_with($dataUrl, 'data:image')) {
                    continue;
                }
                $sort++;
                $filename = "recipe-{$recipe->id}-{$sort}-".time().'.jpg';
                [, $encoded] = explode(',', $dataUrl, 2);
                file_put_contents("$directory/$filename", base64_decode($encoded));
                $recipe->photos()->create([
                    'path' => 'img/recipes/'.$filename,
                    'sort_order' => $sort,
                ]);
            }

            if ($this->is_active || Recipe::where('meal_item_id', $this->mealItemId)->where('is_active', true)->count() === 0) {
                $recipe->activate();
            } else {
                $recipe->mealItem?->recalculateCosts();
            }
        });

        $this->editing = false;
        $this->loadRecipes();
        $this->dispatch('meal-items-updated');
        $this->dispatch('menu-updated');
    }

    public function activateRecipe(int $recipeId): void
    {
        abort_unless($this->canManage, 403);
        $recipe = Recipe::where('meal_item_id', $this->mealItemId)->findOrFail($recipeId);
        $recipe->activate();
        $this->loadRecipes();
        $this->dispatch('meal-items-updated');
        $this->dispatch('menu-updated');
    }

    public function deleteRecipe(int $recipeId): void
    {
        abort_unless($this->canManage, 403);
        $recipe = Recipe::with('photos')->where('meal_item_id', $this->mealItemId)->findOrFail($recipeId);
        foreach ($recipe->photos as $photo) {
            if (file_exists(public_path($photo->path))) {
                @unlink(public_path($photo->path));
            }
        }
        $wasActive = $recipe->is_active;
        $meal = $recipe->mealItem;
        $recipe->delete();

        if ($wasActive && $meal) {
            $next = $meal->recipes()->first();
            if ($next) {
                $next->activate();
            } else {
                $meal->recalculateCosts();
            }
        }

        $this->loadRecipes();
        $this->dispatch('meal-items-updated');
        $this->dispatch('menu-updated');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editing = false;
    }

    protected function loadRecipes(): void
    {
        $this->recipes = Recipe::with(['ingredients', 'photos'])
            ->where('meal_item_id', $this->mealItemId)
            ->latest()
            ->get()
            ->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'title' => $recipe->title,
                'is_active' => $recipe->is_active,
                'ingredient_cost' => $recipe->ingredientCost(),
                'ingredient_count' => $recipe->ingredients->count(),
                'photo_count' => $recipe->photos->count(),
                'training_video_url' => $recipe->training_video_url,
                'instructions' => $recipe->instructions,
                'ingredients' => $recipe->ingredients->map(fn ($i) => [
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'unit' => $i->unit,
                    'cost' => $i->cost,
                ])->all(),
                'photos' => $recipe->photos->pluck('path')->all(),
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.shared.meal-items.recipe-manager-modal');
    }
}
