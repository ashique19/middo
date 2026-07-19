<?php

namespace App\Livewire\Shared;

use App\Models\MealPackage;
use App\Models\PackageSubscription;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $paymentFilter = 'all';

    public ?int $packageFilter = null;

    public string $bulkSkipDate = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public bool $canManage = false;

    public bool $isAdmin = false;

    public function mount(): void
    {
        $role = Auth::user()?->role?->name;
        $this->isAdmin = $role === 'admin';
        $this->canManage = in_array($role, ['admin', 'operation'], true);
        $this->bulkSkipDate = now('Asia/Dhaka')->addDay()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPaymentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPackageFilter(): void
    {
        $this->resetPage();
    }

    public function bulkSkipHoliday(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'bulkSkipDate' => 'required|date',
        ]);

        try {
            $result = app(PackageSubscriptionService::class)->bulkSkipDate(
                Auth::user(),
                $this->bulkSkipDate
            );
            $this->statusMessage = "Skipped {$result['skipped']} package order(s) on {$this->bulkSkipDate}. Refunded ৳".number_format($result['refunded_amount']).'.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function showRoute(int $subscriptionId): string
    {
        $role = Auth::user()?->role?->name;

        return $role === 'admin'
            ? route('admin.subscriptions.show', $subscriptionId)
            : route('operation.subscriptions.show', $subscriptionId);
    }

    public function render()
    {
        $subscriptions = PackageSubscription::query()
            ->with(['user', 'package', 'area'])
            ->withCount([
                'orders',
                'orders as pending_orders_count' => fn ($q) => $q->where('order_status', 'pending'),
            ])
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('id', 'like', $term)
                        ->orWhere('receiver_name', 'like', $term)
                        ->orWhere('receiver_mobile', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('company_name', 'like', $term)
                                ->orWhere('mobile', 'like', $term);
                        })
                        ->orWhereHas('package', fn ($p) => $p->where('name', 'like', $term));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->paymentFilter !== 'all', fn ($q) => $q->where('payment_status', $this->paymentFilter))
            ->when($this->packageFilter, fn ($q) => $q->where('meal_package_id', $this->packageFilter))
            ->orderByDesc('id')
            ->paginate(15);

        $packages = MealPackage::query()->orderBy('name')->get(['id', 'name']);

        return view('livewire.shared.subscriptions.table', [
            'subscriptions' => $subscriptions,
            'packages' => $packages,
        ]);
    }
}
