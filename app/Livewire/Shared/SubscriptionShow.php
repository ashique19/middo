<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubscriptionShow extends Component
{
    public int $subscriptionId;

    public bool $canManage = false;

    public bool $isAdmin = false;

    public string $delivery_time = '';

    public string $address = '';

    public string $receiver_name = '';

    public string $receiver_mobile = '';

    public ?int $swapMenuItemId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(int $subscription): void
    {
        $role = Auth::user()?->role?->name;
        $this->isAdmin = $role === 'admin';
        $this->canManage = in_array($role, ['admin', 'operation'], true);
        $this->subscriptionId = $subscription;

        $model = $this->subscription();
        $this->delivery_time = (string) $model->delivery_time;
        $this->address = (string) $model->address;
        $this->receiver_name = (string) $model->receiver_name;
        $this->receiver_mobile = (string) $model->receiver_mobile;
    }

    protected function subscription(): PackageSubscription
    {
        return PackageSubscription::with([
            'user',
            'package',
            'area.city',
            'orders' => fn ($q) => $q->with(['menuItem', 'orderGroup'])->orderBy('delivery_date'),
        ])->findOrFail($this->subscriptionId);
    }

    public function skipOrder(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        try {
            $order = Order::query()->findOrFail($orderId);
            app(PackageSubscriptionService::class)->skipDayAsStaff(Auth::user(), $order);
            $this->statusMessage = "Skipped package day order #{$orderId} and refunded the corporate wallet.";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function swapOrderMenu(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->swapMenuItemId) {
            $this->errorMessage = 'Pick a menu item to swap to.';

            return;
        }

        try {
            $order = Order::query()->findOrFail($orderId);
            app(PackageSubscriptionService::class)->swapDayMenu(
                Auth::user(),
                $order,
                (int) $this->swapMenuItemId
            );
            $this->statusMessage = "Swapped menu for order #{$orderId} and re-grouped it.";
            $this->errorMessage = null;
            $this->swapMenuItemId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function saveDelivery(): void
    {
        abort_unless($this->canManage, 403);

        try {
            $result = app(PackageSubscriptionService::class)->updateDeliveryDetails(
                Auth::user(),
                $this->subscription(),
                $this->delivery_time,
                $this->address,
                $this->receiver_name,
                $this->receiver_mobile,
            );
            $this->statusMessage = "Updated delivery details on {$result['updated_orders']} future pending order(s).";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function cancelRemaining(): void
    {
        abort_unless($this->canManage, 403);

        try {
            $result = app(PackageSubscriptionService::class)->cancelRemaining(
                Auth::user(),
                $this->subscription()
            );
            $this->statusMessage = "Cancelled {$result['cancelled_orders']} remaining day(s). Refunded ৳".number_format($result['refunded_amount']).'.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function forceComplete(): void
    {
        abort_unless($this->isAdmin, 403);

        try {
            app(PackageSubscriptionService::class)->forceComplete(Auth::user(), $this->subscription());
            $this->statusMessage = 'Subscription marked completed.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function indexRoute(): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.subscriptions.index')
            : route('operation.subscriptions.index');
    }

    public function activeOrdersRoute(): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.orders.active')
            : route('operation.orders.active');
    }

    public function render()
    {
        $subscription = $this->subscription();
        $menuItems = MenuItem::query()->orderBy('name')->get(['id', 'name', 'price']);

        return view('livewire.shared.subscriptions.show', [
            'subscription' => $subscription,
            'menuItems' => $menuItems,
        ])->layout('layouts.private.app', [
            'title' => 'Subscription #'.$subscription->id,
        ]);
    }
}
