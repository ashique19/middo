<?php

namespace App\Livewire\Operation;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\MealOrderGrouper;
use App\Support\OrderGroupManager;
use App\Support\OrdersExcelExport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActiveOrders extends Component
{
    use WithOrdersListView;

    public array $dateSections = [];

    /** @var string[] Expanded date accordion keys */
    public array $expandedDates = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->loadOrders();
    }

    #[On('order-group-kitchen-changed')]
    public function refreshOrders(): void
    {
        $this->loadOrders();
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

    public function autoGroupForDate(string $deliveryDate): void
    {
        $userId = Auth::id();

        if (! $userId) {
            $this->statusMessage = 'You must be logged in to group orders.';

            return;
        }

        $orders = Order::with(['menuItem', 'user', 'orderGroup'])
            ->future()
            ->active()
            ->whereDate('delivery_date', $deliveryDate)
            ->orderBy('delivery_time')
            ->get();

        $assigned = app(MealOrderGrouper::class)->autoGroup($orders, $userId);

        if (! in_array($deliveryDate, $this->expandedDates, true)) {
            $this->expandedDates[] = $deliveryDate;
        }

        $this->loadOrders();

        $this->statusMessage = $assigned > 0
            ? "{$assigned} order(s) auto-grouped for {$this->dateLabel($deliveryDate)}."
            : "No new orders available to auto-group for {$this->dateLabel($deliveryDate)}.";
    }

    public function ungroupOrder(int $orderId): void
    {
        try {
            app(OrderGroupManager::class)->ungroup($orderId);
            $this->loadOrders();
            $this->statusMessage = 'Order removed from group.';
        } catch (\Throwable $e) {
            $this->statusMessage = 'Could not ungroup order.';
        }
    }

    public function handleOrderDrop(int $sourceOrderId, string $targetType, ?int $targetId = null): void
    {
        if ($sourceOrderId <= 0) {
            return;
        }

        try {
            app(OrderGroupManager::class)->handleDrop(
                $sourceOrderId,
                $targetType,
                $targetId,
                Auth::id()
            );

            $this->loadOrders();
            $this->statusMessage = null;
        } catch (\Throwable $e) {
            $this->statusMessage = 'Could not move order: '.$e->getMessage();
        }
    }

    protected function loadOrders(): void
    {
        $orders = Order::with(['menuItem', 'user', 'orderGroup'])
            ->future()
            ->active()
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        $this->dateSections = $orders
            ->groupBy(fn (Order $order) => $order->delivery_date->toDateString())
            ->map(function (Collection $dayOrders, string $date) {
                return $this->buildDateTree($date, $dayOrders);
            })
            ->values()
            ->all();

        if ($this->expandedDates === [] && $this->dateSections !== []) {
            $this->expandedDates = [$this->dateSections[0]['date']];
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

        $groups = OrderGroup::with(['menuItem', 'kitchen', 'orders.menuItem', 'orders.user'])
            ->whereDate('delivery_date', $date)
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (OrderGroup $group, int $index) use ($colorPalette) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'kitchen_label' => $group->kitchenDisplayName(),
                    'total_quantity' => $group->orders->sum('quantity'),
                    'color' => $colorPalette[$index % count($colorPalette)],
                    'orders' => $group->orders
                        ->sortBy('delivery_time')
                        ->values()
                        ->map(fn (Order $order) => $this->formatOrderNode($order, false))
                        ->all(),
                ];
            })
            ->all();

        $groupedOrderIds = collect($groups)
            ->flatMap(fn (array $group) => collect($group['orders'])->pluck('id'));

        $ungrouped = $dayOrders
            ->reject(fn (Order $order) => $groupedOrderIds->contains($order->id))
            ->values()
            ->map(fn (Order $order) => $this->formatOrderNode($order, true))
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

    protected function formatOrderNode(Order $order, bool $isUngrouped): array
    {
        $party = $order->partyPayload();

        return [
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'quantity' => $order->quantity,
            'total_amount' => $order->total_amount,
            'amount_paid' => $party['amount_paid'],
            'amount_due' => $party['amount_due'],
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'address' => $order->address,
            'delivery_date' => $order->delivery_date->toDateString(),
            'is_ungrouped' => $isUngrouped,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'group_name' => $order->orderGroup?->name,
        ];
    }

    public function getFlatOrdersProperty(): array
    {
        $rows = [];
        foreach ($this->dateSections as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['orders'] as $order) {
                    $rows[] = array_merge($order, [
                        'group_name' => $group['name'],
                        'delivery_date' => $section['date'],
                    ]);
                }
            }
            foreach ($section['ungrouped'] as $order) {
                $rows[] = array_merge($order, [
                    'group_name' => 'Ungrouped',
                    'delivery_date' => $section['date'],
                ]);
            }
        }

        return $rows;
    }

    public function exportExcel(): StreamedResponse
    {
        $orders = Order::with(['menuItem', 'user', 'orderGroup'])
            ->future()
            ->active()
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        return OrdersExcelExport::download($orders, 'active-orders-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
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
        return view('livewire.operation.active-orders')
            ->layout('layouts.private.app', ['title' => 'Active Orders']);
    }
}
