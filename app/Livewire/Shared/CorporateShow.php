<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\PackageOrderPresenter;
use App\Support\WalletLedger;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CorporateShow extends Component
{
    use WithPagination;

    public User $corporate;

    public string $adjustDirection = 'credit';

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(User $corporate): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

        $corporate->load(['role', 'city', 'area']);
        abort_unless($corporate->role?->name === 'corporate', 404);

        $this->corporate = $corporate;
    }

    public function indexRoute(): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.corporates.index')
            : route('operation.corporates.index');
    }

    public function subscriptionShowRoute(int $subscriptionId): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.subscriptions.show', $subscriptionId)
            : route('operation.subscriptions.show', $subscriptionId);
    }

    public function canAdjustWallet(): bool
    {
        return in_array(Auth::user()?->role?->name, ['admin', 'operation'], true);
    }

    public function postWalletAdjustment(): void
    {
        abort_unless($this->canAdjustWallet(), 403);

        $this->statusMessage = '';
        $this->errorMessage = '';

        $amount = (int) $this->adjustAmount;
        $reason = trim($this->adjustReason);

        if ($amount < 1) {
            $this->errorMessage = 'Enter a positive adjustment amount.';

            return;
        }

        if ($reason === '') {
            $this->errorMessage = 'A reason is required for wallet adjustments.';

            return;
        }

        if (! in_array($this->adjustDirection, ['credit', 'debit'], true)) {
            $this->errorMessage = 'Choose credit or debit.';

            return;
        }

        try {
            $actor = Auth::user();
            $actorLabel = $actor?->name ?: ('#'.Auth::id());
            $description = 'Ops adjustment by '.$actorLabel.': '.$reason;

            if ($this->adjustDirection === 'credit') {
                WalletLedger::credit(
                    $this->corporate,
                    $amount,
                    WalletTransaction::TYPE_ADJUSTMENT,
                    $description,
                    $actor
                );
            } else {
                WalletLedger::debit(
                    $this->corporate,
                    $amount,
                    $description,
                    $actor
                );
            }

            $this->corporate->refresh();
            $this->statusMessage = ucfirst($this->adjustDirection).' ৳'.number_format($amount).' posted to wallet.';
            $this->adjustAmount = '';
            $this->adjustReason = '';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not post wallet adjustment.';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatOrder(Order $order): array
    {
        $party = $order->partyPayload();

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date->toDateString(),
            'quantity' => $order->quantity,
            'order_status' => $order->order_status,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'total_amount' => $order->total_amount,
            'address' => $order->address,
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'group_name' => $order->orderGroup?->name,
        ], PackageOrderPresenter::fields($order));
    }

    public function render()
    {
        $orders = Order::query()
            ->with(['menuItem', 'user', 'orderGroup', 'packageSubscription.package'])
            ->where('user_id', $this->corporate->id)
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(15);

        $orderRows = collect($orders->items())
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->values()
            ->all();

        $transactions = WalletTransaction::query()
            ->where('user_id', $this->corporate->id)
            ->latest()
            ->limit(20)
            ->get();

        $subscriptions = PackageSubscription::query()
            ->with('package')
            ->where('user_id', $this->corporate->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $today = now('Asia/Dhaka')->toDateString();
        $stats = [
            'balance' => (int) $this->corporate->balance,
            'total_orders' => Order::query()->where('user_id', $this->corporate->id)->count(),
            'upcoming_orders' => Order::query()
                ->where('user_id', $this->corporate->id)
                ->where('delivery_date', '>=', $today)
                ->where('order_status', '!=', 'cancelled')
                ->count(),
            'active_subscriptions' => PackageSubscription::query()
                ->where('user_id', $this->corporate->id)
                ->where('status', PackageSubscription::STATUS_ACTIVE)
                ->count(),
        ];

        return view('livewire.shared.corporates.show', [
            'orders' => $orders,
            'orderRows' => $orderRows,
            'transactions' => $transactions,
            'subscriptions' => $subscriptions,
            'stats' => $stats,
        ])->layout('layouts.private.app', [
            'title' => ($this->corporate->company_name ?: $this->corporate->name).' · Corporate',
        ]);
    }
}
