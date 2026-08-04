<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use App\Models\Order;
use App\Support\OrderCancellation;
use App\Support\OrderKitchenActions;
use App\Support\OrderLens;
use App\Support\OrderOpsForce;
use App\Support\OrderPaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class OrderShow extends Component
{
    public Order $order;

    #[Url(as: 'lens', history: true)]
    public string $lens = OrderLens::MIDDO;

    public string $forceReason = '';

    public ?string $forceMessage = null;

    public ?string $forceError = null;

    public function mount(Order $order): void
    {
        $actor = Auth::user();
        abort_unless($actor, 403);

        $role = $actor->role?->name;
        $allowed = OrderLens::allowedFor($actor, $order->loadMissing('orderGroup'));
        abort_if($allowed === [], 403);

        $requested = OrderLens::normalize($this->lens !== '' ? $this->lens : request()->query('lens'));
        if (! in_array($requested, $allowed, true)) {
            $requested = $allowed[0];
        }

        // Native roles stay on their fixed lens (no middo switcher).
        if (! OrderLens::isStaff($role)) {
            $requested = OrderLens::defaultForRole($role);
        }

        $this->lens = $requested;
        OrderLens::assertCanView($actor, $order, $this->lens);
        $this->reloadOrder($order);
    }

    public function updatedLens(string $value): void
    {
        $actor = Auth::user();
        abort_unless($actor, 403);

        $next = OrderLens::normalize($value);
        if (! OrderLens::isStaff($actor->role?->name)) {
            $next = OrderLens::defaultForRole($actor->role?->name);
        }

        OrderLens::assertCanView($actor, $this->order, $next);
        $this->lens = $next;
        $this->forceMessage = null;
        $this->forceError = null;
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
            'orderGroup.orders.menuItem',
            'orderGroup.orders.user',
            'deliveryRider.role',
            'packageSubscription.package',
            'logs.performedBy',
            'createdBy',
            'updatedBy',
            'moneyEvents',
            'partnerPayables.beneficiary',
            'middoBoxes',
            'complaints',
        ]);

        $this->order = $order;
    }

    public function switchLens(string $lens): void
    {
        $this->updatedLens($lens);
    }

    public function forceCancel(): void
    {
        $this->assertStaff();
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
        $this->assertStaff();
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

    public function corporateCancel(): void
    {
        $this->forceMessage = null;
        $this->forceError = null;
        $actor = Auth::user();

        try {
            if (OrderLens::isStaff($actor->role?->name)) {
                $result = OrderCancellation::cancelPendingAsStaff($actor, $this->order);
            } else {
                $result = OrderCancellation::cancelPendingOwnedBy($actor, (int) $this->order->id);
            }
            $refund = (int) $result['refunded_amount'];
            $this->forceMessage = $refund > 0
                ? "Order cancelled. Wallet refunded ৳{$refund}."
                : 'Order cancelled.';
            $this->reloadOrder($result['order']);
        } catch (ValidationException $e) {
            $this->forceError = collect($e->errors())->flatten()->first() ?: $e->getMessage();
        } catch (\Throwable $e) {
            $this->forceError = $e->getMessage();
        }
    }

    public function markReady(): void
    {
        $this->forceMessage = null;
        $this->forceError = null;

        try {
            $order = OrderKitchenActions::markReady($this->order, Auth::user());
            $this->forceMessage = 'Order marked ready for packing / dispatch.';
            $this->reloadOrder($order);
        } catch (\Throwable $e) {
            $this->forceError = $e->getMessage();
        }
    }

    protected function assertStaff(): void
    {
        abort_unless(OrderLens::isStaff(Auth::user()?->role?->name), 403);
    }

    protected function rolePrefix(): string
    {
        return match (Auth::user()?->role?->name) {
            'admin' => 'admin',
            'kitchen' => 'kitchen',
            'delivery' => 'delivery',
            'corporate' => 'corporates',
            default => 'operation',
        };
    }

    public function backRoute(): string
    {
        $role = Auth::user()?->role?->name;

        return match ($role) {
            'kitchen' => route('kitchen.orders.active'),
            'delivery' => route('delivery.kitchen-dispatches'),
            'corporate' => Route::has('corporates.orders.scheduled')
                ? route('corporates.orders.scheduled')
                : url('/'),
            'admin' => route('admin.orders.active'),
            default => route('operation.orders.active'),
        };
    }

    public function canSwitchLenses(): bool
    {
        return OrderLens::isStaff(Auth::user()?->role?->name);
    }

    /**
     * @return list<string>
     */
    public function availableLenses(): array
    {
        return OrderLens::allowedFor(Auth::user(), $this->order);
    }

    public function corporateShowRoute(): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $user = $this->order->user;
        if (! $user || $user->role?->name !== 'corporate') {
            return null;
        }

        $name = $this->rolePrefix().'.corporates.show';

        return Route::has($name) ? route($name, $user) : null;
    }

    public function kitchenOrdersRoute(): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $kitchen = $this->order->orderGroup?->kitchen;
        if (! $kitchen) {
            return null;
        }

        return route($this->rolePrefix().'.kitchens.orders', $kitchen);
    }

    public function kitchenShowRoute(): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $kitchen = $this->order->orderGroup?->kitchen;
        if (! $kitchen || $kitchen->role?->name !== 'kitchen') {
            return null;
        }

        $name = $this->rolePrefix().'.kitchens.show';

        return Route::has($name) ? route($name, $kitchen) : null;
    }

    public function deliveryShowRoute(): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $rider = $this->order->deliveryRider;
        if (! $rider || $rider->role?->name !== 'delivery') {
            return null;
        }

        $name = $this->rolePrefix().'.deliveries.show';

        return Route::has($name) ? route($name, $rider) : null;
    }

    public function menuShowRoute(?MenuItem $menuItem = null): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $menuItem ??= $this->order->menuItem;
        if (! $menuItem) {
            return null;
        }

        $name = $this->rolePrefix().'.menu.show';

        return Route::has($name) ? route($name, $menuItem) : null;
    }

    public function subscriptionShowRoute(): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $subscriptionId = $this->order->package_subscription_id;
        if (! $subscriptionId) {
            return null;
        }

        return route($this->rolePrefix().'.subscriptions.show', $subscriptionId);
    }

    public function accountsHubRoute(): ?string
    {
        if (! OrderLens::isStaff(Auth::user()?->role?->name)) {
            return null;
        }

        $name = $this->rolePrefix().'.accounts.index';

        return Route::has($name) ? route($name) : null;
    }

    public function orderShowUrl(int $orderId, ?string $lens = null): ?string
    {
        $role = Auth::user()?->role?->name;
        $name = match ($role) {
            'admin' => 'admin.orders.show',
            'kitchen' => 'kitchen.orders.show',
            'delivery' => 'delivery.orders.show',
            default => 'operation.orders.show',
        };

        if (! Route::has($name)) {
            return null;
        }

        $url = route($name, $orderId);
        if ($lens && OrderLens::isStaff($role)) {
            $url .= '?lens='.urlencode(OrderLens::normalize($lens));
        }

        return $url;
    }

    public function render()
    {
        $payload = OrderLens::payload($this->order, $this->lens, Auth::user());

        return view('livewire.shared.orders.show', [
            'payload' => $payload,
            'party' => $payload['party'],
            'paymentMethodLabel' => $payload['party']['payment_method_label']
                ?? OrderPaymentMethod::label($this->order->payment_method),
            'logs' => $payload['tracking'],
            'corporate' => $this->order->user,
            'kitchen' => $this->order->orderGroup?->kitchen,
            'group' => $this->order->orderGroup,
            'rider' => $this->order->deliveryRider,
            'moneyTree' => $this->lens === OrderLens::MIDDO ? $payload['money'] : null,
            'lensMoney' => $payload['money'],
            'lensContext' => $payload['context'],
            'lensActions' => $payload['actions'],
            'availableLenses' => $this->availableLenses(),
        ])->layout('layouts.private.app', [
            'title' => 'Order #'.$this->order->id,
        ]);
    }
}
