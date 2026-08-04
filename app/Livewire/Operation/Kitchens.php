<?php

namespace App\Livewire\Operation;

use App\Models\Order;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\KitchenTier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class Kitchens extends Component
{
    public array $kitchenSections = [];

    /** @var string[] */
    public array $expandedKitchens = [];

    public function mount(): void
    {
        $this->loadKitchens();
    }

    #[On('order-group-kitchen-changed')]
    public function refreshKitchens(): void
    {
        $this->loadKitchens();
    }

    public function toggleKitchen(string $kitchenKey): void
    {
        if (in_array($kitchenKey, $this->expandedKitchens, true)) {
            $this->expandedKitchens = array_values(array_filter(
                $this->expandedKitchens,
                fn (string $key) => $key !== $kitchenKey
            ));
        } else {
            $this->expandedKitchens[] = $kitchenKey;
        }
    }

    protected function loadKitchens(): void
    {
        $kitchenUsers = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $activeOrders = Order::with(['menuItem', 'user', 'orderGroup.menuItem', 'orderGroup.kitchen'])
            ->future()
            ->active()
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        $sections = $kitchenUsers
            ->map(function (User $kitchen) use ($activeOrders) {
                $kitchenOrders = $activeOrders->filter(
                    fn (Order $order) => $order->orderGroup?->kitchen_id === $kitchen->id
                );

                $section = $this->buildKitchenSection(
                    (string) $kitchen->id,
                    $kitchen->name,
                    $kitchenOrders
                );

                $tier = KitchenTier::normalize($kitchen->kitchen_tier);
                $remaining = KitchenCapacity::remainingSlots($kitchen);
                $allowed = KitchenCapacity::effectiveAllowedOpenGroups($kitchen);
                $open = KitchenCapacity::openGroupCount((int) $kitchen->id);

                $areaName = null;
                $cityName = null;
                if ($kitchen->area_id) {
                    $area = \App\Models\Area::query()->with('city')->find($kitchen->area_id);
                    $areaName = $area?->name;
                    $cityName = $area?->city?->name;
                }

                return array_merge($section, [
                    'tier' => $tier,
                    'tier_label' => KitchenTier::label($tier),
                    'remaining_slots' => $remaining,
                    'open_groups' => $open,
                    'allowed_open_groups' => $allowed,
                    'area_name' => $areaName,
                    'city_name' => $cityName,
                    'at_capacity' => $remaining <= 0,
                ]);
            })
            ->all();

        usort($sections, function (array $a, array $b) {
            if ($a['active_count'] !== $b['active_count']) {
                return $b['active_count'] <=> $a['active_count'];
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $this->kitchenSections = $sections;

        if ($this->expandedKitchens === []) {
            $firstWithOrders = collect($sections)->first(fn (array $s) => $s['active_count'] > 0);

            if ($firstWithOrders) {
                $this->expandedKitchens = [$firstWithOrders['key']];
            }
        }
    }

    protected function buildKitchenSection(string $key, string $name, Collection $orders): array
    {
        $earliestDate = $orders->min(fn (Order $order) => $order->delivery_date->toDateString());

        return [
            'key' => $key,
            'name' => $name,
            'active_count' => $orders->count(),
            'date_label' => $earliestDate ? $this->dateLabel($earliestDate) : '—',
            ...$this->buildKitchenTree($orders),
        ];
    }

    protected function buildKitchenTree(Collection $orders): array
    {
        $colorPalette = [
            'bg-amber-50/80 border-amber-200',
            'bg-sky-50/80 border-sky-200',
            'bg-emerald-50/80 border-emerald-200',
            'bg-violet-50/80 border-violet-200',
            'bg-rose-50/80 border-rose-200',
            'bg-teal-50/80 border-teal-200',
            'bg-orange-50/80 border-orange-200',
            'bg-indigo-50/80 border-indigo-200',
        ];

        $groupedOrders = $orders->filter(fn (Order $order) => $order->orderGroup !== null);

        $groups = $groupedOrders
            ->groupBy(fn (Order $order) => $order->orderGroup->id)
            ->values()
            ->map(function (Collection $groupOrders, int $index) use ($colorPalette) {
                $group = $groupOrders->first()->orderGroup;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'total_quantity' => $groupOrders->sum('quantity'),
                    'color' => $colorPalette[$index % count($colorPalette)],
                    'orders' => $groupOrders
                        ->sortBy('delivery_time')
                        ->values()
                        ->map(fn (Order $order) => $this->formatOrderNode($order))
                        ->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'groups' => $groups,
        ];
    }

    protected function formatOrderNode(Order $order): array
    {
        $party = $order->partyPayload();

        return [
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date->toDateString(),
            'quantity' => $order->quantity,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'group_name' => $order->orderGroup?->name,
        ];
    }

    protected function dateLabel(string $date): string
    {
        $today = now('Asia/Dhaka')->toDateString();
        $tomorrow = now('Asia/Dhaka')->copy()->addDay()->toDateString();

        if ($date === $today) {
            return 'Today';
        }

        if ($date === $tomorrow) {
            return 'Tomorrow';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }

    public function render()
    {
        return view('livewire.operation.kitchens')
            ->layout('layouts.private.app', ['title' => 'Kitchens']);
    }
}
