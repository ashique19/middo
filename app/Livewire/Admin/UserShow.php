<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\UserLog;
use App\Support\PackageOrderPresenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UserShow extends Component
{
    use WithPagination;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);

        $user->load(['role', 'city', 'area']);
        $this->user = $user;
    }

    public function listRoute(): string
    {
        $role = $this->user->role?->name;

        return match ($role) {
            'admin' => route('admin.users.admin'),
            'operation' => route('admin.users.operation'),
            'kitchen' => route('admin.users.kitchen'),
            'delivery' => route('admin.users.delivery'),
            'corporate' => route('admin.corporates.index'),
            default => route('admin.users.index'),
        };
    }

    public function listLabel(): string
    {
        $role = $this->user->role?->name;

        return match ($role) {
            'corporate' => 'Corporates',
            'admin', 'operation', 'kitchen', 'delivery' => ucfirst($role).' users',
            default => 'Users',
        };
    }

    /**
     * @return list<array{label: string, href: string, description: string}>
     */
    public function relatedLinks(): array
    {
        $role = $this->user->role?->name;
        $links = [];

        if ($role === 'corporate') {
            $links[] = [
                'label' => 'Corporate profile',
                'href' => route('admin.corporates.show', $this->user),
                'description' => 'Balance, wallet, subscriptions, and full order history',
            ];
            $links[] = [
                'label' => 'Active orders',
                'href' => route('admin.orders.active'),
                'description' => 'Browse active orders across the platform',
            ];
            $links[] = [
                'label' => 'Menus',
                'href' => route('admin.menu.index'),
                'description' => 'Menu catalog used when placing corporate orders',
            ];
        }

        if ($role === 'kitchen') {
            $links[] = [
                'label' => 'Kitchen profile',
                'href' => route('admin.kitchens.show', $this->user),
                'description' => 'Kitchen details and linked order history',
            ];
            $links[] = [
                'label' => 'Kitchen orders',
                'href' => route('admin.kitchens.orders', $this->user),
                'description' => 'All orders assigned to this kitchen',
            ];
            $links[] = [
                'label' => 'Menus',
                'href' => route('admin.menu.index'),
                'description' => 'Menu items prepared by kitchens',
            ];
        }

        if ($role === 'delivery') {
            $links[] = [
                'label' => 'Delivery profile',
                'href' => route('admin.deliveries.show', $this->user),
                'description' => 'Rider details and delivery order history',
            ];
            $links[] = [
                'label' => 'Active orders',
                'href' => route('admin.orders.active'),
                'description' => 'Orders currently in delivery',
            ];
        }

        if (in_array($role, ['admin', 'operation'], true)) {
            $links[] = [
                'label' => 'Active orders',
                'href' => route('admin.orders.active'),
                'description' => 'Ops order queue',
            ];
            $links[] = [
                'label' => 'Menus',
                'href' => route('admin.menu.index'),
                'description' => 'Menu catalog',
            ];
            $links[] = [
                'label' => 'Corporates',
                'href' => route('admin.corporates.index'),
                'description' => 'Corporate directory',
            ];
        }

        return $links;
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
            'menu_item_id' => $order->menu_item_id,
            'group_name' => $order->orderGroup?->name,
            'show_url' => route('admin.orders.show', $order),
            'menu_url' => $order->menu_item_id
                ? route('admin.menu.show', $order->menu_item_id)
                : null,
        ], PackageOrderPresenter::fields($order));
    }

    protected function ordersQuery()
    {
        $role = $this->user->role?->name;
        $query = Order::query()
            ->with(['menuItem', 'user', 'orderGroup', 'packageSubscription.package']);

        return match ($role) {
            'corporate' => $query->where('user_id', $this->user->id),
            'kitchen' => $query->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $this->user->id)),
            'delivery' => $query->where('delivery_rider_id', $this->user->id),
            default => $query->whereRaw('0 = 1'),
        };
    }

    public function eventLabel(string $event): string
    {
        return match ($event) {
            UserLog::EVENT_LOGIN => 'Login',
            UserLog::EVENT_LOGOUT => 'Logout',
            UserLog::EVENT_LOGIN_FAILED => 'Login failed',
            UserLog::EVENT_LOGIN_BLOCKED => 'Login blocked',
            UserLog::EVENT_CREATED => 'Account created',
            UserLog::EVENT_UPDATED => 'Profile updated',
            UserLog::EVENT_DELETED => 'Account deleted',
            UserLog::EVENT_STATUS_CHANGED => 'Status changed',
            UserLog::EVENT_PASSWORD_CHANGED => 'Password changed',
            UserLog::EVENT_PASSWORD_RESET => 'Password reset',
            default => str_replace('_', ' ', ucfirst($event)),
        };
    }

    public function render()
    {
        $role = $this->user->role?->name;
        $showOrders = in_array($role, ['corporate', 'kitchen', 'delivery'], true);

        $orders = null;
        $orderRows = [];

        if ($showOrders) {
            $orders = $this->ordersQuery()
                ->orderByDesc('delivery_date')
                ->orderByDesc('id')
                ->paginate(10, pageName: 'ordersPage');

            $orderRows = collect($orders->items())
                ->map(fn (Order $order) => $this->formatOrder($order))
                ->values()
                ->all();
        }

        $logs = UserLog::query()
            ->with('performedBy')
            ->where('user_id', $this->user->id)
            ->orderByDesc('id')
            ->paginate(20, pageName: 'logsPage');

        $displayName = $this->user->company_name
            ?: ($this->user->name ?: trim($this->user->first_name.' '.$this->user->last_name));

        return view('livewire.admin.users.show', [
            'displayName' => $displayName,
            'relatedLinks' => $this->relatedLinks(),
            'showOrders' => $showOrders,
            'orders' => $orders,
            'orderRows' => $orderRows,
            'logs' => $logs,
        ])->layout('layouts.private.app', [
            'title' => $displayName.' · User',
        ]);
    }
}
