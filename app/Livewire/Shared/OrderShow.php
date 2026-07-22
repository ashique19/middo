<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use App\Models\Order;
use App\Support\CorporateApiPresenter;
use App\Support\OrderMoneyFlow;
use App\Support\OrderPaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class OrderShow extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

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
        ]);

        $this->order = $order;
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
