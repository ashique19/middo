<?php

namespace App\Livewire\Operation;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\OrdersExcelExport;
use App\Support\PackageOrderPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderHistory extends Component
{
    use WithOrdersListView;
    use WithPagination;

    /** @var string[] */
    public array $expandedDates = [];

    /** all|package|alacarte */
    public string $packageFilter = 'all';

    public function mount(): void
    {
        $this->setDefaultExpandedDate();
    }

    public function updatedPackageFilter(): void
    {
        if (! in_array($this->packageFilter, ['all', 'package', 'alacarte'], true)) {
            $this->packageFilter = 'all';
        }

        $this->resetPage();
        $this->expandedDates = [];
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
            ->orderByDesc('id')
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
                        ->sortByDesc('id')
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
            ->sortByDesc('id')
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

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'quantity' => $order->quantity,
            'total_amount' => $order->total_amount,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'address' => $order->address,
            'delivery_date' => $order->delivery_date->toDateString(),
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'kitchen_label' => $order->orderGroup?->kitchenDisplayName() ?? 'Unassigned',
            'order_group_id' => $order->orderGroup?->id,
            'group_name' => $order->orderGroup?->name,
        ], PackageOrderPresenter::fields($order));
    }

    public function exportExcel(): StreamedResponse
    {
        $orders = Order::with(['menuItem', 'user', 'orderGroup', 'packageSubscription.package'])
            ->past()
            ->when($this->packageFilter === 'package', fn ($q) => $q->whereNotNull('package_subscription_id'))
            ->when($this->packageFilter === 'alacarte', fn ($q) => $q->whereNull('package_subscription_id'))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        return OrdersExcelExport::download($orders, 'order-history-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
    }

    public function render()
    {
        $orders = Order::with([
            'menuItem',
            'user',
            'orderGroup.kitchen',
            'orderGroup.menuItem',
            'packageSubscription.package',
        ])
            ->past()
            ->when($this->packageFilter === 'package', fn ($q) => $q->whereNotNull('package_subscription_id'))
            ->when($this->packageFilter === 'alacarte', fn ($q) => $q->whereNull('package_subscription_id'))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(20);

        $dateSections = $this->buildDateSections(collect($orders->items()));
        $flatOrders = collect($dateSections)
            ->flatMap(function (array $section) {
                $rows = [];
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

                return $rows;
            })
            // Keep list view strictly newest delivery date, then newest order id.
            ->sortByDesc(fn (array $order) => sprintf(
                '%s-%010d',
                $order['delivery_date'],
                (int) $order['id']
            ))
            ->values()
            ->all();

        return view('livewire.operation.order-history', [
            'orders' => $orders,
            'dateSections' => $dateSections,
            'flatOrders' => $flatOrders,
        ])->layout('layouts.private.app', ['title' => 'Order History']);
    }
}
