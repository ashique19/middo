<?php

namespace App\Support;

use App\Models\OrderGroup;
use Illuminate\Support\Collection;

class KitchenIngredientRollup
{
    /**
     * Build a shopping-list style ingredient rollup for a kitchen's accepted groups on a delivery date.
     *
     * @return array{
     *     delivery_date: string,
     *     group_count: int,
     *     plate_count: int,
     *     menus: list<array{menu_id: int, menu_name: string, total_qty: int, group_ids: list<int>, missing_recipes: list<string>}>,
     *     ingredients: list<array{name: string, unit: string, quantity: float, key: string, sources: list<array{menu_id: int, menu_name: string, meal_item: string, per_plate: float, plates: int, total: float}>}>,
     *     warnings: list<string>
     * }
     */
    public static function forKitchen(int $kitchenId, string $deliveryDate): array
    {
        $groups = OrderGroup::query()
            ->with([
                'menuItem.mealItems.activeRecipe.ingredients',
                'orders' => fn ($q) => $q->where('order_status', '!=', 'cancelled'),
            ])
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', $deliveryDate)
            ->orderBy('id')
            ->get()
            ->filter(fn (OrderGroup $group) => $group->orders->isNotEmpty())
            ->values();

        $menuBuckets = [];
        foreach ($groups as $group) {
            $menuId = (int) ($group->menu_id ?: 0);
            if ($menuId < 1) {
                continue;
            }

            $qty = (int) $group->orders->sum('quantity');
            if ($qty < 1) {
                continue;
            }

            if (! isset($menuBuckets[$menuId])) {
                $menuBuckets[$menuId] = [
                    'menu_id' => $menuId,
                    'menu_name' => $group->menuItem?->name ?? 'Menu #'.$menuId,
                    'total_qty' => 0,
                    'group_ids' => [],
                    'menu' => $group->menuItem,
                ];
            }

            $menuBuckets[$menuId]['total_qty'] += $qty;
            $menuBuckets[$menuId]['group_ids'][] = (int) $group->id;
        }

        $ingredientMap = [];
        $menusOut = [];
        $warnings = [];

        foreach ($menuBuckets as $bucket) {
            $menu = $bucket['menu'];
            $plates = (int) $bucket['total_qty'];
            $missing = [];

            $mealItems = $menu?->mealItems ?? collect();
            foreach ($mealItems as $mealItem) {
                $recipe = $mealItem->activeRecipe;
                if (! $recipe) {
                    $missing[] = $mealItem->name;
                    $warnings[] = "No active recipe for meal item “{$mealItem->name}” on menu “{$bucket['menu_name']}”.";

                    continue;
                }

                foreach ($recipe->ingredients as $line) {
                    $name = trim((string) $line->name);
                    if ($name === '') {
                        continue;
                    }

                    $unit = trim((string) ($line->unit ?: ''));
                    $perPlate = (float) $line->quantity;
                    $total = $perPlate * $plates;
                    $key = self::mergeKey($name, $unit);

                    if (! isset($ingredientMap[$key])) {
                        $ingredientMap[$key] = [
                            'name' => $name,
                            'unit' => $unit,
                            'quantity' => 0.0,
                            'key' => $key,
                            'sources' => [],
                        ];
                    }

                    $ingredientMap[$key]['quantity'] += $total;
                    $ingredientMap[$key]['sources'][] = [
                        'menu_id' => (int) $bucket['menu_id'],
                        'menu_name' => $bucket['menu_name'],
                        'meal_item' => $mealItem->name,
                        'per_plate' => $perPlate,
                        'plates' => $plates,
                        'total' => $total,
                    ];
                }
            }

            if ($mealItems->isEmpty()) {
                $warnings[] = "Menu “{$bucket['menu_name']}” has no meal items attached.";
            }

            $menusOut[] = [
                'menu_id' => (int) $bucket['menu_id'],
                'menu_name' => $bucket['menu_name'],
                'total_qty' => $plates,
                'group_ids' => $bucket['group_ids'],
                'missing_recipes' => $missing,
            ];
        }

        $ingredients = Collection::make($ingredientMap)
            ->sortBy(fn (array $row) => mb_strtolower($row['name']).'|'.mb_strtolower($row['unit']), SORT_NATURAL)
            ->values()
            ->map(function (array $row) {
                $row['quantity'] = self::roundQty($row['quantity']);
                $row['sources'] = array_map(function (array $source) {
                    $source['per_plate'] = self::roundQty($source['per_plate']);
                    $source['total'] = self::roundQty($source['total']);

                    return $source;
                }, $row['sources']);

                return $row;
            })
            ->all();

        return [
            'delivery_date' => $deliveryDate,
            'group_count' => $groups->count(),
            'plate_count' => (int) collect($menusOut)->sum('total_qty'),
            'menus' => $menusOut,
            'ingredients' => $ingredients,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public static function mergeKey(string $name, string $unit): string
    {
        return mb_strtolower(trim($name)).'|'.mb_strtolower(trim($unit));
    }

    public static function roundQty(float $qty): float
    {
        return round($qty, 4);
    }
}
