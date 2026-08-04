<?php

namespace App\Support;

use App\Models\OrderComplaint;
use Illuminate\Database\Eloquent\Builder;

class KitchenComplaints
{
    /**
     * Root complaints for orders currently assigned to this kitchen.
     */
    public static function scopedRootsQuery(int $kitchenId): Builder
    {
        return OrderComplaint::query()
            ->whereNull('parent_id')
            ->whereHas('order.orderGroup', function ($query) use ($kitchenId) {
                $query->where('kitchen_id', $kitchenId);
            });
    }

    public static function belongsToKitchen(OrderComplaint $complaint, int $kitchenId): bool
    {
        $complaint->loadMissing('order.orderGroup');

        return (int) ($complaint->order?->orderGroup?->kitchen_id) === $kitchenId;
    }

    public static function categoryLabel(?string $category): string
    {
        return match ($category) {
            'delivery' => 'Delivery',
            'food_quality' => 'Food quality',
            'payment' => 'Payment',
            'other' => 'Other',
            default => $category ? ucfirst(str_replace('_', ' ', $category)) : 'General',
        };
    }
}
