<?php

namespace App\Livewire\Operation;

use App\Models\Order;
use App\Models\OrderGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OrderHistory extends Component
{
    use WithPagination;

    /** @var string[] */
    public array $expandedDates = [];

    public function mount(): void
    {
        $this->setDefaultExpandedDate();
    }

    public function updatedPage(): void
    {
        $this->expandedDates = [];
        $this->setDefaultExpandedDate();
    }

    #[On('order-group-kitchen-changed')]
    public function refreshOrders(): void
    {
        $this->resetPage();
        $this->expandedDates = [];
        $this->setDefaultExpandedDate();
    }

    public function toggleDate(string $deliveryDate): void
    {
        if (in_array($deliveryDate, $this->expandedDates, true)) {
            $this->expandedDates = array_values(array_filter(
                $this->expandedDates,
                fn (string $date) => $date !== $deliveryDate
            ));
        } else {
            $this->expandedDates[] = $deliveryDate;
        }
    }

    protected function buildDateSections(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (Order $order) => $order->delivery_date->toDateString())
            ->sortKeysDesc()
            ->map(function (Collection $dayOrders, string $date) {
                return $this->buildDateTree($date, $dayOrders);
            })
            ->values()
            ->all();
    }

    protected function setDefaultExpandedDate(): void
    {
        $orders = Order::query()
            ->past()
            ->orderByDesc('delivery_date')
            ->orderByDesc('delivery_time')
            ->forPage($this->getPage(), 20)
            ->get();

        $first = $orders->first();

        if ($first) {
            $this->expandedDates = [$first->delivery_date->toDateString()];
        }
    }

    protected function buildDateTree(string $date, Collection $dayOrders): array
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

        $groupIds = $dayOrders
            ->map(fn (Order $order) => $order->orderGroup?->id)
            ->filter()
            ->unique()
            ->values();

        $groups = OrderGroup::with(['menuItem', 'kitchen'])
            ->whereIn('id', $groupIds)
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (OrderGroup $group, int $index) use ($dayOrders, $colorPalette) {
                $groupOrders = $dayOrders->filter(
                    fn (Order $order) => $order->orderGroup?->id === $group->id
                );

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'kitchen_label' => $group->kitchenDisplayName(),
                    'total_quantity' => $groupOrders->sum('quantity'),
                    'color' => $colorPalette[$index % count($colorPalette)],
                    'orders' => $groupOrders
                        ->sortByDesc('delivery_time')
                        ->values()
                        ->map(fn (Order $order) => $this->formatOrderNode($order))
                        ->all(),
                ];
            })
            ->all();

        $groupedOrderIds = collect($groups)
            ->flatMap(fn (array $group) => collect($group['orders'])->pluck('id'));

        $ungrouped = $dayOrders
            ->reject(fn (Order $order) => $groupedOrderIds->contains($order->id))
            ->sortByDesc('delivery_time')
            ->values()
            ->map(fn (Order $order) => $this->formatOrderNode($order))
            ->all();

        return [
            'date' => $date,
            'label' => $this->dateLabel($date),
            'count' => $dayOrders->count(),
            'total_quantity' => $dayOrders->sum('quantity'),
            'groups' => $groups,
            'ungrouped' => $ungrouped,
        ];
    }

    protected function dateLabel(string $date): string
    {
        $yesterday = now('Asia/Dhaka')->copy()->subDay()->toDateString();

        if ($date === $yesterday) {
            return 'Yesterday';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }

    protected function formatOrderNode(Order $order): array
    {
        $party = $order->partyPayload();

        return [
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'quantity' => $order->quantity,
            'total_amount' => $order->total_amount,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'address' => $order->address,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'kitchen_label' => $order->orderGroup?->kitchenDisplayName() ?? 'Unassigned',
            'order_group_id' => $order->orderGroup?->id,
        ];
    }

    public function render()
    {
        $orders = Order::with(['menuItem', 'user', 'orderGroup.kitchen', 'orderGroup.menuItem'])
            ->past()
            ->orderByDesc('delivery_date')
            ->orderByDesc('delivery_time')
            ->paginate(20);

        $dateSections = $this->buildDateSections(collect($orders->items()));

        return view('livewire.operation.order-history', [
            'orders' => $orders,
            'dateSections' => $dateSections,
        ])->layout('layouts.private.app', ['title' => 'Order History']);
    }
}
