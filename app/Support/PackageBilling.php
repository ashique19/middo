<?php

namespace App\Support;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Helpers for monthly meal package day selection and totals.
 *
 * Weekday indices match Carbon::dayOfWeek: 0=Sunday … 6=Saturday.
 *
 * Corporate flow: pick menus + day counts for a target month, omit weekdays,
 * prepay on selection totals. Operations later assigns exact dates.
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
     * Normalize target month to Y-m.
     */
    public static function normalizeTargetMonth(?string $month, ?CarbonInterface $now = null): string
    {
        $now = ($now ?? now(OrderCutoff::timezone()))->copy()->timezone(OrderCutoff::timezone());

        if (! filled($month)) {
            return $now->format('Y-m');
        }

        return Carbon::createFromFormat('Y-m', $month, OrderCutoff::timezone())
            ->startOfMonth()
            ->format('Y-m');
    }

    /**
     * Calendar bounds for a Y-m target month.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public static function monthBounds(string $targetMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', self::normalizeTargetMonth($targetMonth), OrderCutoff::timezone())
            ->startOfMonth()
            ->startOfDay();

        return [
            'start' => $start->copy(),
            'end' => $start->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * Eligible delivery dates in a month after omitting weekdays and past cutoffs.
     *
     * @param  array<int>  $omittedWeekdays
     * @return Collection<int, string> Y-m-d dates
     */
    public static function availableDatesInMonth(
        string $targetMonth,
        array $omittedWeekdays = [],
        ?CarbonInterface $now = null
    ): Collection {
        $omitted = self::normalizeOmittedWeekdays($omittedWeekdays);
        $now = ($now ?? now(OrderCutoff::timezone()))->copy()->timezone(OrderCutoff::timezone());
        $bounds = self::monthBounds($targetMonth);
        $dates = collect();

        $cursor = $bounds['start']->copy();
        while ($cursor->lte($bounds['end'])) {
            $dateKey = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;

            if (! in_array($dow, $omitted, true) && ! OrderCutoff::isPastForDeliveryDate($cursor, $now)) {
                $dates->push($dateKey);
            }

            $cursor->addDay();
        }

        return $dates->values();
    }

    /**
     * @param  array<int, array{menu_item_id:int, day_count:int}>  $selections
     * @return array<int, array{menu_item_id:int, day_count:int, menu_item_name:?string, unit_price:int, line_total:int}>
     */
    public static function normalizeSelections(array $selections): array
    {
        $normalized = [];

        foreach ($selections as $row) {
            $menuItemId = (int) ($row['menu_item_id'] ?? 0);
            $dayCount = (int) ($row['day_count'] ?? 0);

            if ($menuItemId < 1 || $dayCount < 1) {
                continue;
            }

            if (! isset($normalized[$menuItemId])) {
                $normalized[$menuItemId] = [
                    'menu_item_id' => $menuItemId,
                    'day_count' => 0,
                ];
            }

            $normalized[$menuItemId]['day_count'] += $dayCount;
        }

        if ($normalized === []) {
            return [];
        }

        $menus = MenuItem::query()
            ->whereIn('id', array_keys($normalized))
            ->get(['id', 'name', 'price'])
            ->keyBy('id');

        foreach ($normalized as $menuItemId => $row) {
            if (! $menus->has($menuItemId)) {
                throw new RuntimeException('One or more selected menu items are invalid.');
            }
            $menu = $menus[$menuItemId];
            $normalized[$menuItemId]['menu_item_name'] = $menu->name;
            $normalized[$menuItemId]['unit_price'] = (int) $menu->price;
        }

        return array_values($normalized);
    }

    /**
     * Quote a corporate-built monthly package from menu day-count selections.
     * Food total = Σ(menu.price × day_count × quantity).
     *
     * @param  array<int, array{menu_item_id:int, day_count:int}>  $selections
     * @param  array<int>  $omittedWeekdays
     */
    public static function quoteFromSelections(
        MealPackage $package,
        int $quantity,
        array $selections,
        array $omittedWeekdays,
        string $targetMonth,
        ?CarbonInterface $now = null
    ): array {
        $quantity = max(1, $quantity);
        $omittedWeekdays = self::normalizeOmittedWeekdays($omittedWeekdays);
        $targetMonth = self::normalizeTargetMonth($targetMonth, $now);
        $normalized = self::normalizeSelections($selections);
        $billableDays = (int) collect($normalized)->sum('day_count');
        $available = self::availableDatesInMonth($targetMonth, $omittedWeekdays, $now);
        $availableDays = $available->count();
        $bounds = self::monthBounds($targetMonth);

        $total = 0;
        foreach ($normalized as $index => $row) {
            $lineTotal = (int) $row['unit_price'] * (int) $row['day_count'] * $quantity;
            $normalized[$index]['line_total'] = $lineTotal;
            $total += $lineTotal;
        }

        // Compatibility snapshot: weighted average menu price (not used for billing).
        $pricePerDay = $billableDays > 0
            ? (int) floor($total / ($billableDays * $quantity))
            : 0;

        return [
            'billable_days' => $billableDays,
            'price_per_day' => $pricePerDay,
            'quantity' => $quantity,
            'total_amount' => $total,
            'target_month' => $targetMonth,
            'start_date' => $bounds['start']->toDateString(),
            'end_date' => $bounds['end']->toDateString(),
            'available_days' => $availableDays,
            'fills_month' => $billableDays === $availableDays && $availableDays > 0,
            'omitted_weekdays' => $omittedWeekdays,
            'selections' => $normalized,
            'days' => [], // exact dates assigned later by operations
        ];
    }

    /**
     * Corporate monthly packages must cover every working day in the month
     * (after omitted weekdays / off-days).
     */
    public static function assertSelectionsFillMonth(array $quote): void
    {
        $available = (int) ($quote['available_days'] ?? 0);
        $selected = (int) ($quote['billable_days'] ?? 0);

        if ($available < 1) {
            throw new RuntimeException(
                'No working days remain in '.$quote['target_month']
                .' with the current off-days. Un-omit weekdays or pick another month.'
            );
        }

        if ($selected !== $available) {
            throw new RuntimeException(
                'Select menus for all '.$available.' working days this month'
                .' (currently '.$selected.'). Every working day must be filled.'
            );
        }
    }

    /**
     * Legacy calendar-based billable days (published package templates with fixed days).
     *
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
