<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PackageInsights extends Component
{
    use WithPagination;

    public string $walletFilter = 'package';

    public function updatingWalletFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $activeSubscribers = PackageSubscription::query()->active()->count();
        $prepaidRevenue = (int) PackageSubscription::query()->sum('amount_paid');
        $packageRefunds = (int) WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_REFUND)
            ->where('description', 'like', '%Package%')
            ->sum('amount');
        $pendingPackageOrders = Order::query()
            ->whereNotNull('package_subscription_id')
            ->where('order_status', 'pending')
            ->count();

        $byPackage = PackageSubscription::query()
            ->select('meal_package_id', DB::raw('COUNT(*) as sub_count'), DB::raw('SUM(amount_paid) as revenue'))
            ->groupBy('meal_package_id')
            ->with('package:id,name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $walletQuery = WalletTransaction::query()
            ->with('user')
            ->orderByDesc('id');

        if ($this->walletFilter === 'package') {
            $walletQuery->where(function ($q) {
                $q->where('description', 'like', '%Package%')
                    ->orWhere('reference_type', PackageSubscription::class)
                    ->orWhere(function ($inner) {
                        $inner->where('reference_type', Order::class)
                            ->whereIn('reference_id', function ($sub) {
                                $sub->select('id')
                                    ->from('orders')
                                    ->whereNotNull('package_subscription_id');
                            });
                    });
            });
        }

        $walletEntries = $walletQuery->paginate(20);

        return view('livewire.shared.packages.insights', [
            'activeSubscribers' => $activeSubscribers,
            'prepaidRevenue' => $prepaidRevenue,
            'packageRefunds' => $packageRefunds,
            'pendingPackageOrders' => $pendingPackageOrders,
            'byPackage' => $byPackage,
            'walletEntries' => $walletEntries,
        ])->layout('layouts.private.app', ['title' => 'Package Insights']);
    }
}
