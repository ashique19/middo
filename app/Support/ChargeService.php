<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\PackageSubscription;
use App\Models\PackageSubscriptionCharge;
use Illuminate\Support\Collection;

class ChargeService
{
    public const CONTEXT_ORDER = 'order';

    public const CONTEXT_PACKAGE = 'package';

    /**
     * Quote applicable charges for a single-menu cart (corporate menu checkout).
     *
     * @param  array<string, int>  $quantitiesByDate  date => qty (0 qty ignored)
     * @return array{
     *     total: int,
     *     lines: list<array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int,menu_item_id:?int}>,
     *     per_order: array<string, list<array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int}>>
     * }
     */
    public function quoteOrderCart(?int $areaId, int $menuItemId, array $quantitiesByDate): array
    {
        $active = collect($quantitiesByDate)
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0);

        $deliveryCount = $active->count();
        $itemQuantity = (int) $active->sum();

        $charges = $this->applicableCharges(self::CONTEXT_ORDER, $areaId, $menuItemId);
        $aggregated = $this->buildLines($charges, $deliveryCount, $itemQuantity, $menuItemId);

        $perOrder = [];
        foreach ($active as $date => $qty) {
            $dateLines = [];
            foreach ($charges as $charge) {
                if ($charge->calculation === Charge::CALC_PER_CHECKOUT) {
                    continue;
                }
                $built = $this->lineForScope($charge, 1, (int) $qty, $menuItemId);
                if ($built !== null) {
                    $dateLines[] = $built;
                }
            }
            $perOrder[(string) $date] = $dateLines;
        }

        // One-time checkout fees attach to the first delivery date only.
        if ($active->isNotEmpty()) {
            $firstDate = (string) $active->keys()->first();
            foreach ($charges as $charge) {
                if ($charge->calculation !== Charge::CALC_PER_CHECKOUT) {
                    continue;
                }
                $built = $this->lineForScope($charge, 1, $itemQuantity, $menuItemId);
                if ($built !== null) {
                    $perOrder[$firstDate][] = $built;
                }
            }
        }

        return [
            'total' => $aggregated['total'],
            'lines' => $aggregated['lines'],
            'per_order' => $perOrder,
        ];
    }

    /**
     * Quote charges for a monthly package subscription.
     *
     * @param  array<int, array{menu_item_id:int, day_count:int}>  $selections
     * @return array{
     *     total: int,
     *     lines: list<array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int,menu_item_id:?int}>
     * }
     */
    public function quotePackage(?int $areaId, int $packageQuantity, array $selections): array
    {
        $packageQuantity = max(1, $packageQuantity);
        $lines = [];
        $total = 0;
        $billableDays = 0;
        $totalItems = 0;

        foreach ($selections as $selection) {
            $menuItemId = (int) ($selection['menu_item_id'] ?? 0);
            $dayCount = max(0, (int) ($selection['day_count'] ?? 0));
            if ($menuItemId < 1 || $dayCount < 1) {
                continue;
            }

            $billableDays += $dayCount;
            $totalItems += $dayCount * $packageQuantity;

            $charges = $this->applicableCharges(self::CONTEXT_PACKAGE, $areaId, $menuItemId)
                ->filter(fn (Charge $c) => $c->calculation !== Charge::CALC_PER_CHECKOUT);

            foreach ($charges as $charge) {
                $built = $this->lineForScope($charge, $dayCount, $dayCount * $packageQuantity, $menuItemId);
                if ($built === null) {
                    continue;
                }
                $lines[] = $built;
                $total += $built['amount'];
            }
        }

        // Global / area-scoped per_checkout charges (menu_item_id null) apply once.
        $checkoutCharges = $this->applicableCharges(self::CONTEXT_PACKAGE, $areaId, null)
            ->filter(fn (Charge $c) => $c->calculation === Charge::CALC_PER_CHECKOUT
                && $c->menu_item_id === null);

        foreach ($checkoutCharges as $charge) {
            $built = $this->lineForScope($charge, max(1, $billableDays), max(1, $totalItems), null);
            if ($built === null) {
                continue;
            }
            $lines[] = $built;
            $total += $built['amount'];
        }

        return [
            'total' => $total,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int}>  $lines
     */
    public function attachToOrder(Order $order, array $lines): int
    {
        $sum = 0;
        foreach ($lines as $line) {
            $amount = (int) ($line['amount'] ?? 0);
            if ($amount < 1) {
                continue;
            }
            OrderCharge::create([
                'order_id' => $order->id,
                'charge_id' => $line['charge_id'] ?? null,
                'name' => (string) $line['name'],
                'category' => (string) $line['category'],
                'calculation' => (string) $line['calculation'],
                'unit_amount' => (int) $line['unit_amount'],
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'amount' => $amount,
            ]);
            $sum += $amount;
        }

        return $sum;
    }

    /**
     * @param  list<array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int,menu_item_id:?int}>  $lines
     */
    public function attachToPackage(PackageSubscription $subscription, array $lines): int
    {
        $sum = 0;
        foreach ($lines as $line) {
            $amount = (int) ($line['amount'] ?? 0);
            if ($amount < 1) {
                continue;
            }
            PackageSubscriptionCharge::create([
                'package_subscription_id' => $subscription->id,
                'charge_id' => $line['charge_id'] ?? null,
                'menu_item_id' => $line['menu_item_id'] ?? null,
                'name' => (string) $line['name'],
                'category' => (string) $line['category'],
                'calculation' => (string) $line['calculation'],
                'unit_amount' => (int) $line['unit_amount'],
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'amount' => $amount,
            ]);
            $sum += $amount;
        }

        return $sum;
    }

    /**
     * @return Collection<int, Charge>
     */
    public function applicableCharges(string $context, ?int $areaId, ?int $menuItemId): Collection
    {
        $now = now(OrderCutoff::timezone());

        return Charge::query()
            ->where('is_active', true)
            ->where(function ($q) use ($context) {
                if ($context === self::CONTEXT_ORDER) {
                    $q->whereIn('applies_to', [Charge::APPLIES_ORDERS, Charge::APPLIES_BOTH]);
                } else {
                    $q->whereIn('applies_to', [Charge::APPLIES_PACKAGES, Charge::APPLIES_BOTH]);
                }
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) use ($areaId) {
                $q->whereNull('area_id');
                if ($areaId) {
                    $q->orWhere('area_id', $areaId);
                }
            })
            ->where(function ($q) use ($menuItemId) {
                $q->whereNull('menu_item_id');
                if ($menuItemId) {
                    $q->orWhere('menu_item_id', $menuItemId);
                }
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (Charge $charge) => $charge->matchesScope($areaId, $menuItemId))
            ->values();
    }

    /**
     * @param  Collection<int, Charge>  $charges
     * @return array{total:int, lines:list<array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int,menu_item_id:?int}>}
     */
    protected function buildLines(Collection $charges, int $deliveryCount, int $itemQuantity, ?int $menuItemId): array
    {
        $lines = [];
        $total = 0;

        foreach ($charges as $charge) {
            $built = $this->lineForScope($charge, $deliveryCount, $itemQuantity, $menuItemId);
            if ($built === null) {
                continue;
            }
            $lines[] = $built;
            $total += $built['amount'];
        }

        return ['total' => $total, 'lines' => $lines];
    }

    /**
     * @return array{charge_id:int,name:string,category:string,calculation:string,unit_amount:int,quantity:int,amount:int,menu_item_id:?int}|null
     */
    protected function lineForScope(Charge $charge, int $deliveryCount, int $itemQuantity, ?int $menuItemId): ?array
    {
        $unit = (int) $charge->amount;
        if ($unit < 1) {
            return null;
        }

        $qty = match ($charge->calculation) {
            Charge::CALC_PER_ITEM => max(0, $itemQuantity),
            Charge::CALC_PER_DELIVERY => max(0, $deliveryCount),
            Charge::CALC_PER_CHECKOUT => 1,
            default => 0,
        };

        if ($qty < 1) {
            return null;
        }

        return [
            'charge_id' => (int) $charge->id,
            'name' => (string) $charge->name,
            'category' => (string) $charge->category,
            'calculation' => (string) $charge->calculation,
            'unit_amount' => $unit,
            'quantity' => $qty,
            'amount' => $unit * $qty,
            'menu_item_id' => $menuItemId,
        ];
    }
}
