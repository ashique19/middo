<?php

namespace App\Livewire\Operation;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Order;
use App\Support\OrdersExcelExport;
use App\Support\PackageOrderPresenter;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SearchOrder extends Component
{
    use WithOrdersListView;

    public string $search = '';

    public array $orders = [];

    /** all|package|alacarte */
    public string $packageFilter = 'all';

    public function updatedSearch(): void
    {
        $this->searchOrders();
    }

    public function updatedPackageFilter(): void
    {
        if (! in_array($this->packageFilter, ['all', 'package', 'alacarte'], true)) {
            $this->packageFilter = 'all';
        }

        $this->searchOrders();
    }

    public function mount(): void
    {
        $this->searchOrders();
    }

    protected function searchOrders(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            $this->orders = [];

            return;
        }

        $this->orders = Order::with(['menuItem', 'user', 'packageSubscription.package'])
            ->where(function ($query) use ($term) {
                if (is_numeric($term)) {
                    $query->where('id', $term);
                }

                $query->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('delivery_date', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('mobile', 'like', "%{$term}%");
                    })
                    ->orWhereHas('menuItem', function ($menuQuery) use ($term) {
                        $menuQuery->where('name', 'like', "%{$term}%");
                    });
            })
            ->when($this->packageFilter === 'package', fn ($q) => $q->whereNotNull('package_subscription_id'))
            ->when($this->packageFilter === 'alacarte', fn ($q) => $q->whereNull('package_subscription_id'))
            ->orderByDesc('delivery_date')
            ->orderByDesc('delivery_time')
            ->limit(50)
            ->get()
            ->map(function (Order $order) {
                $row = $order->toArray();
                $party = $order->partyPayload();
                $row['payment_method'] = $party['payment_method'];
                $row['payment_method_label'] = $party['payment_method_label'];
                $row['customer_name'] = $party['customer_name'];
                $row['has_separate_receiver'] = $party['has_separate_receiver'];
                $row['account_holder_name'] = $party['account_holder_name'];

                return array_merge($row, PackageOrderPresenter::fields($order));
            })
            ->all();
    }

    public function exportExcel(): StreamedResponse
    {
        $term = trim($this->search);
        $query = Order::with(['menuItem', 'user', 'orderGroup']);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->where('id', $term);
                }

                $q->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('delivery_date', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('mobile', 'like', "%{$term}%");
                    })
                    ->orWhereHas('menuItem', function ($menuQuery) use ($term) {
                        $menuQuery->where('name', 'like', "%{$term}%");
                    });
            });
        }

        $orders = $query
            ->orderByDesc('delivery_date')
            ->orderByDesc('delivery_time')
            ->limit(2000)
            ->get();

        return OrdersExcelExport::download($orders, 'search-orders-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
    }

    public function render()
    {
        return view('livewire.operation.search-order')
            ->layout('layouts.private.app', ['title' => 'Search Order']);
    }
}
