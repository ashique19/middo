<?php

namespace App\Support;

use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderLog;
use App\Models\PackageSubscription;
use App\Models\User;
use App\Support\OrderAudit;

class CorporateApiPresenter
{
    public static function user(User $user): array
    {
        $user->loadMissing('role');

        // User table also has legacy string `area`/`city` columns that shadow
        // the same-named BelongsTo relations, so resolve via foreign keys.
        $areaName = $user->area_id
            ? \App\Models\Area::query()->whereKey($user->area_id)->value('name')
            : ($user->getAttributes()['area'] ?? null);
        $cityName = $user->city_id
            ? \App\Models\City::query()->whereKey($user->city_id)->value('name')
            : ($user->getAttributes()['city'] ?? null);

        return [
            'id' => $user->id,
            'company_name' => filled($user->company_name)
                ? $user->company_name
                : ($user->name !== '' ? $user->name : 'Corporate Partner'),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'balance' => (int) $user->balance,
            'address' => $user->address,
            'area' => $areaName,
            'city' => $cityName,
            'area_id' => $user->area_id,
            'city_id' => $user->city_id,
            'role' => $user->role?->name,
        ];
    }

    public static function menuItem(MenuItem $item): array
    {
        $image = $item->thumbnail;
        if ($image && ! preg_match('/^https?:\/\//', $image)) {
            $image = asset(ltrim($image, '/'));
        }

        return [
            'id' => (string) $item->id,
            'name' => $item->name,
            'description' => $item->summary ?? '',
            'price' => (float) $item->price,
            'image' => $image ?: asset('img/default.jpg'),
            'tags' => self::inferTags($item),
        ];
    }

    public static function order(Order $order): array
    {
        $order->loadMissing('menuItem');

        return [
            'id' => (string) $order->id,
            'menu_item' => $order->menuItem
                ? self::menuItem($order->menuItem)
                : null,
            'delivery_date' => optional($order->delivery_date)->toDateString(),
            'delivery_time' => $order->delivery_time,
            'quantity' => (int) $order->quantity,
            'total_amount' => (float) $order->total_amount,
            'amount_paid' => (float) ($order->amount_paid ?? 0),
            'prepaid_amount' => (float) ($order->prepaid_amount ?? 0),
            'amount_due' => (float) $order->amountDue(),
            'address' => $order->address,
            'receiver_name' => $order->receiver_name,
            'receiver_mobile' => $order->receiver_mobile,
            'account_holder_name' => $order->accountHolderName(),
            'has_separate_receiver' => $order->hasSeparateReceiver(),
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->paymentMethodKey(),
            'payment_method_label' => $order->paymentMethodLabel(),
            'paid' => $order->payment_status === 'paid',
            'package_subscription_id' => $order->package_subscription_id
                ? (string) $order->package_subscription_id
                : null,
            'can_skip' => $order->package_subscription_id
                && $order->order_status === 'pending'
                && OrderCutoff::allowsModification($order),
            'can_delete' => OrderCutoff::allowsModification($order),
            'is_history' => optional($order->delivery_date)->lt(now('Asia/Dhaka')->startOfDay()) ?? false,
        ];
    }

    public static function mealPackage(MealPackage $package, bool $withDays = false): array
    {
        $image = $package->thumbnail;
        if ($image && ! preg_match('/^https?:\/\//', $image)) {
            $image = asset(ltrim($image, '/'));
        }

        $payload = [
            'id' => (string) $package->id,
            'name' => $package->name,
            'summary' => $package->summary ?? '',
            'price_per_day' => (int) $package->price_per_day,
            'diet_tag' => $package->diet_tag,
            'duration_days' => (int) $package->duration_days,
            'start_date' => optional($package->start_date)->toDateString(),
            'end_date' => optional($package->end_date)->toDateString(),
            'thumbnail' => $image,
            'days_count' => $package->days_count ?? $package->days()->count(),
            'is_rate_plan' => true,
        ];

        if ($withDays) {
            $package->loadMissing('days.menuItem');
            $payload['days'] = $package->days->map(fn ($day) => [
                'date' => $day->delivery_date->toDateString(),
                'weekday' => (int) $day->delivery_date->dayOfWeek,
                'menu_item' => $day->menuItem ? self::menuItem($day->menuItem) : null,
            ])->values()->all();
        }

        return $payload;
    }

    public static function packageSubscription(PackageSubscription $subscription): array
    {
        $subscription->loadMissing(['package', 'orders.menuItem', 'selections.menuItem']);

        return [
            'id' => (string) $subscription->id,
            'package' => $subscription->package
                ? self::mealPackage($subscription->package)
                : null,
            'quantity' => (int) $subscription->quantity,
            'start_date' => optional($subscription->start_date)->toDateString(),
            'end_date' => optional($subscription->end_date)->toDateString(),
            'target_month' => $subscription->target_month,
            'omitted_weekdays' => $subscription->omitted_weekdays ?? [],
            'billable_days' => (int) $subscription->billable_days,
            'price_per_day' => (int) $subscription->price_per_day,
            'total_amount' => (int) $subscription->total_amount,
            'amount_paid' => (int) $subscription->amount_paid,
            'payment_status' => $subscription->payment_status,
            'status' => $subscription->status,
            'schedule_status' => $subscription->schedule_status,
            'delivery_time' => $subscription->delivery_time,
            'address' => $subscription->address,
            'receiver_name' => $subscription->receiver_name,
            'receiver_mobile' => $subscription->receiver_mobile,
            'selections' => $subscription->selections->map(fn ($sel) => [
                'menu_item_id' => (int) $sel->menu_item_id,
                'day_count' => (int) $sel->day_count,
                'menu_item' => $sel->menuItem ? self::menuItem($sel->menuItem) : null,
            ])->values()->all(),
            'orders' => $subscription->orders
                ->sortBy('delivery_date')
                ->values()
                ->map(fn (Order $order) => self::order($order))
                ->all(),
            'weekday_labels' => PackageBilling::WEEKDAY_LABELS,
        ];
    }

    public static function trackEvent(OrderLog $log): array
    {
        $payload = [
            ...$log->toArray(),
            'performer_name' => $log->performedBy?->name,
        ];

        return [
            'id' => $log->id,
            'event' => $log->event,
            'title' => self::logLabel($log->event),
            'description' => self::logDescription($payload),
            'performer_name' => $payload['performer_name'],
            'at' => optional($log->created_at)?->timezone('Asia/Dhaka')->toIso8601String(),
            'is_current' => false,
        ];
    }

    public static function supportMessage(OrderComplaint $entry): array
    {
        return [
            'id' => $entry->id,
            'from_support' => (bool) $entry->is_reply,
            'category' => $entry->category,
            'category_label' => self::categoryLabel($entry->category),
            'body' => $entry->message,
            'attachment' => $entry->attachment ? asset(ltrim($entry->attachment, '/')) : null,
            'author_name' => $entry->createdBy?->name ?? ($entry->is_reply ? 'Middo Support' : 'You'),
            'at' => optional($entry->created_at)?->timezone('Asia/Dhaka')->toIso8601String(),
        ];
    }

    public static function availableDates(): array
    {
        $dhakaNow = now(OrderCutoff::timezone());
        $isPastCutoff = OrderCutoff::isPastForDeliveryDate($dhakaNow);
        $startOffset = $isPastCutoff ? 1 : 0;
        $dates = [];

        for ($i = $startOffset; $i < ($startOffset + 9); $i++) {
            $dates[] = $dhakaNow->copy()->addDays($i)->format('Y-m-d');
        }

        return [
            'is_past_cutoff' => $isPastCutoff,
            'cutoff_label' => OrderCutoff::label(),
            'dates' => $dates,
            'delivery_windows' => ['12:00 PM', '11:30 AM'],
            'cities' => self::citiesWithAreas(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, areas: list<array{id: int, name: string}>}>
     */
    public static function citiesWithAreas(): array
    {
        return \App\Models\City::query()
            ->with(['areas' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn ($city) => [
                'id' => (int) $city->id,
                'name' => (string) $city->name,
                'areas' => $city->areas
                    ->map(fn ($area) => [
                        'id' => (int) $area->id,
                        'name' => (string) $area->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public static function logLabel(string $event): string
    {
        return OrderAudit::label($event);
    }

    public static function logDescription(array $log): string
    {
        return OrderAudit::description($log);
    }

    public static function categoryLabel(?string $category): string
    {
        return match ($category) {
            'delivery' => 'Delivery Issue',
            'food_quality' => 'Food Quality',
            'payment' => 'Payment Issue',
            'other' => 'Other',
            default => 'Support',
        };
    }

    private static function inferTags(MenuItem $item): array
    {
        $haystack = strtolower(($item->name ?? '').' '.($item->summary ?? ''));
        $tags = [];

        if (str_contains($haystack, 'veg') || str_contains($haystack, 'paneer')) {
            $tags[] = 'Veg';
        }
        if (str_contains($haystack, 'thali') || str_contains($haystack, 'combo')) {
            $tags[] = 'Thalis';
        }
        if (str_contains($haystack, 'light') || str_contains($haystack, 'salad') || str_contains($haystack, 'bowl')) {
            $tags[] = 'Light';
        }
        if (str_contains($haystack, 'chicken') || str_contains($haystack, 'beef') || str_contains($haystack, 'fish') || str_contains($haystack, 'mutton')) {
            $tags[] = 'Protein';
        }

        return $tags !== [] ? $tags : ['Thalis'];
    }
}
