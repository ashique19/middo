<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use App\Models\Order;
use App\Support\CorporateApiPresenter;
use App\Support\OrderMoneyFlow;
use App\Support\OrderOpsForce;
use App\Support\OrderPaymentMethod;
use App\Support\OrderTransition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class OrderShow extends Component
{
    public Order $order;

    public string $forceReason = '';

    public ?string $forceMessage = null;

    public ?string $forceError = null;

    public function mount(Order $order): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

        $this->reloadOrder($order);
    }

    protected function reloadOrder(Order $order): void
    {
        $order->load([
            'user.role',
            'user.city',
            'user.area',
            'menuItem',
            'area.city',
            'orderGroup.kitchen.role',
            'orderGroup.menuItem',
            'deliveryRider.role',
            'packageSubscription.package',
            'logs.performedBy',
            'createdBy',
            'updatedBy',
            'moneyEvents',
            'partnerPayables.beneficiary',
            'middoBoxes',
        ]);

        $this->order = $order;
    }

    public function canForceCancel(): bool
    {
        $status = (string) $this->order->order_status;

        return in_array($status, ['pending', OrderTransition::PROCESSING, OrderTransition::READY], true)
            && $this->order->dispatched_at === null;
    }

    public function canReleaseRider(): bool
    {
        return (string) $this->order->order_status === OrderTransition::ON_THE_WAY_TO_DELIVERY
            && $this->order->delivery_rider_id !== null;
    }

    public function forceCancel(): void
    {
        $this->forceMessage = null;
        $this->forceError = null;

        try {
            $result = OrderOpsForce::cancelBeforePacked(
                $this->order,
                Auth::user(),
                $this->forceReason !== '' ? $this->forceReason : null
            );
            $refund = (int) $result['refunded_amount'];
            $this->forceMessage = $refund > 0
                ? "Order cancelled. Wallet refunded ৳{$refund}."
                : 'Order cancelled.';
            $this->forceReason = '';
            $this->reloadOrder($result['order']);
        } catch (\Throwable $e) {
            $this->forceError = $e->getMessage();
        }
    }

    public function releaseRider(): void
    {
        $this->forceMessage = null;
        $this->forceError = null;

        try {
            $order = OrderOpsForce::releaseRiderToPacked(
                $this->order,
                Auth::user(),
                $this->forceReason !== '' ? $this->forceReason : null
            );
            $this->forceMessage = 'Rider released. Order is packed and awaiting accept again.';
            $this->forceReason = '';
            $this->reloadOrder($order);
        } catch (\Throwable $e) {
            $this->forceError = $e->getMessage();
        }
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function backRoute(): string
    {
        return route($this->rolePrefix().'.orders.active');
    }

    public function corporateShowRoute(): ?string
    {
        $user = $this->order->user;
        if (! $user || $user->role?->name !== 'corporate') {
            return null;
        }

        $name = $this->rolePrefix().'.corporates.show';

        return Route::has($name) ? route($name, $user) : null;
    }

    public function kitchenOrdersRoute(): ?string
    {
        $kitchen = $this->order->orderGroup?->kitchen;
        if (! $kitchen) {
            return null;
        }

        return route($this->rolePrefix().'.kitchens.orders', $kitchen);
    }

    public function kitchenShowRoute(): ?string
    {
        $kitchen = $this->order->orderGroup?->kitchen;
        if (! $kitchen || $kitchen->role?->name !== 'kitchen') {
            return null;
        }

        $name = $this->rolePrefix().'.kitchens.show';

        return Route::has($name) ? route($name, $kitchen) : null;
    }

    public function deliveryShowRoute(): ?string
    {
        $rider = $this->order->deliveryRider;
        if (! $rider || $rider->role?->name !== 'delivery') {
            return null;
        }

        $name = $this->rolePrefix().'.deliveries.show';

        return Route::has($name) ? route($name, $rider) : null;
    }

    public function menuShowRoute(?MenuItem $menuItem = null): ?string
    {
        $menuItem ??= $this->order->menuItem;
        if (! $menuItem) {
            return null;
        }

        $name = $this->rolePrefix().'.menu.show';

        return Route::has($name) ? route($name, $menuItem) : null;
    }

    public function subscriptionShowRoute(): ?string
    {
        $subscriptionId = $this->order->package_subscription_id;
        if (! $subscriptionId) {
            return null;
        }

        return route($this->rolePrefix().'.subscriptions.show', $subscriptionId);
    }

    public function render()
    {
        $party = $this->order->partyPayload();
        $logs = $this->order->logs
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($log) {
                $event = CorporateApiPresenter::trackEvent($log);

                return array_merge($event, [
                    'at_label' => optional($log->created_at)?->timezone('Asia/Dhaka')->format('M d, Y g:i A'),
                ]);
            })
            ->all();

        return view('livewire.shared.orders.show', [
            'party' => $party,
            'paymentMethodLabel' => $party['payment_method_label']
                ?? OrderPaymentMethod::label($this->order->payment_method),
            'logs' => $logs,
            'corporate' => $this->order->user,
            'kitchen' => $this->order->orderGroup?->kitchen,
            'group' => $this->order->orderGroup,
            'rider' => $this->order->deliveryRider,
            'moneyTree' => OrderMoneyFlow::treeForOrder($this->order),
        ])->layout('layouts.private.app', [
            'title' => 'Order #'.$this->order->id,
        ]);
    }
}
