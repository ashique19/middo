<?php

namespace App\Support;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Helpers for 30-day meal package day selection and totals.
 *
 * Weekday indices match Carbon::dayOfWeek: 0=Sunday … 6=Saturday.
 */
class PackageBilling
{
    public const WEEKDAY_LABELS = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    /**
     * @param  array<int>  $omittedWeekdays
     * @param  array<string>  $extraSkippedDates  Y-m-d dates to skip individually
     * @return Collection<int, MealPackageDay>
     */
    public static function billableDays(
        MealPackage $package,
        array $omittedWeekdays = [],
        array $extraSkippedDates = [],
        ?CarbonInterface $now = null
    ): Collection {
        $omitted = collect($omittedWeekdays)->map(fn ($d) => (int) $d)->unique()->values()->all();
        $skipped = collect($extraSkippedDates)->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->all();
        $now = ($now ?? now(OrderCutoff::timezone()))->copy()->timezone(OrderCutoff::timezone());

        return $package->days()
            ->with('menuItem')
            ->orderBy('delivery_date')
            ->get()
            ->filter(function (MealPackageDay $day) use ($omitted, $skipped, $now) {
                $date = $day->delivery_date->copy()->timezone(OrderCutoff::timezone());
                $dateKey = $date->toDateString();

                if (in_array($dateKey, $skipped, true)) {
                    return false;
                }

                if (in_array((int) $date->dayOfWeek, $omitted, true)) {
                    return false;
                }

                if (OrderCutoff::isPastForDeliveryDate($date, $now)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  array<int>  $omittedWeekdays
     * @param  array<string>  $extraSkippedDates
     */
    public static function quote(
        MealPackage $package,
        int $quantity,
        array $omittedWeekdays = [],
        array $extraSkippedDates = [],
        ?CarbonInterface $now = null
    ): array {
        $quantity = max(1, $quantity);
        $days = self::billableDays($package, $omittedWeekdays, $extraSkippedDates, $now);
        $billableDays = $days->count();
        $pricePerDay = (int) $package->price_per_day;
        $total = $billableDays * $pricePerDay * $quantity;

        return [
            'billable_days' => $billableDays,
            'price_per_day' => $pricePerDay,
            'quantity' => $quantity,
            'total_amount' => $total,
            'days' => $days->map(fn (MealPackageDay $day) => [
                'date' => $day->delivery_date->toDateString(),
                'weekday' => (int) $day->delivery_date->dayOfWeek,
                'menu_item_id' => $day->menu_item_id,
                'menu_item_name' => $day->menuItem?->name,
                'line_total' => $pricePerDay * $quantity,
            ])->values()->all(),
        ];
    }

    public static function normalizeOmittedWeekdays(array $weekdays): array
    {
        return collect($weekdays)
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 0 && $d <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
