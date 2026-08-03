<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use App\Models\PackageSubscription;
use App\Support\PackageBilling;
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

    /** @var array<string, int|null> date => menu_item_id */
    public array $scheduleAssignments = [];

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
        $this->resetScheduleAssignments($model);
    }

    protected function subscription(): PackageSubscription
    {
        return PackageSubscription::with([
            'user',
            'package',
            'area.city',
            'selections.menuItem',
            'orders' => fn ($q) => $q->with(['menuItem', 'orderGroup'])->orderBy('delivery_date'),
        ])->findOrFail($this->subscriptionId);
    }

    protected function resetScheduleAssignments(?PackageSubscription $model = null): void
    {
        $model ??= $this->subscription();
        $this->scheduleAssignments = [];

        if (! $model->canReceiveScheduleAssignments()) {
            return;
        }

        $month = (string) ($model->target_month ?: $model->start_date->format('Y-m'));
        $dates = PackageBilling::availableDatesInMonth($month, $model->omitted_weekdays ?? []);
        $confirmedDates = $model->orders
            ->where('order_status', '!=', 'cancelled')
            ->map(fn ($order) => $order->delivery_date->toDateString())
            ->all();

        foreach ($dates as $date) {
            if (in_array($date, $confirmedDates, true)) {
                continue;
            }
            $this->scheduleAssignments[$date] = null;
        }
    }

    public function assignDateMenu(string $date, $menuItemId): void
    {
        abort_unless($this->canManage, 403);

        if (! array_key_exists($date, $this->scheduleAssignments)) {
            return;
        }

        $menuItemId = $menuItemId === '' || $menuItemId === null ? null : (int) $menuItemId;
        $this->scheduleAssignments[$date] = $menuItemId;
    }

    public function saveSchedule(): void
    {
        abort_unless($this->canManage, 403);
        $this->errorMessage = null;
        $this->statusMessage = null;

        $assignments = collect($this->scheduleAssignments)
            ->filter(fn ($menuItemId) => filled($menuItemId))
            ->map(fn ($menuItemId, $date) => [
                'date' => $date,
                'menu_item_id' => (int) $menuItemId,
            ])
            ->values()
            ->all();

        try {
            $result = app(PackageSubscriptionService::class)->assignSchedule(
                Auth::user(),
                $this->subscription(),
                $assignments
            );
            $remaining = $result['subscription']->remainingBillableDays();
            $this->statusMessage = $remaining > 0
                ? 'Confirmed '.$result['orders']->count().' day(s). '.$remaining.' day(s) still unconfirmed.'
                : 'Confirmed '.$result['orders']->count().' day(s). Package schedule is complete.';
            $this->resetScheduleAssignments($result['subscription']);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function skipOrder(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        try {
            $order = Order::query()->findOrFail($orderId);
            $result = app(PackageSubscriptionService::class)->skipDayAsStaff(Auth::user(), $order);
            $this->statusMessage = "Skipped package day order #{$orderId} and refunded ৳".number_format($result['refunded_amount']).' to the corporate wallet.';
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

    public function selectionRemaining(PackageSubscription $subscription): array
    {
        $draftAssigned = collect($this->scheduleAssignments)
            ->filter()
            ->countBy(fn ($id) => (int) $id);

        $remaining = $subscription->remainingSelectionCounts();

        return $subscription->selections->map(function ($sel) use ($draftAssigned, $remaining) {
            $menuId = (int) $sel->menu_item_id;
            $left = (int) ($remaining[$menuId] ?? 0);
            $draft = (int) ($draftAssigned[$menuId] ?? 0);

            return [
                'menu_item_id' => $menuId,
                'name' => $sel->menuItem?->name ?? 'Menu',
                'unit_price' => (int) $sel->unit_price,
                'day_count' => (int) $sel->day_count,
                'assigned' => max(0, (int) $sel->day_count - $left) + $draft,
                'remaining' => max(0, $left - $draft),
            ];
        })->values()->all();
    }

    public function render()
    {
        $subscription = $this->subscription();
        $selectionMenus = $subscription->selections->pluck('menuItem')->filter()->values();

        return view('livewire.shared.subscriptions.show', [
            'subscription' => $subscription,
            'menuItems' => $selectionMenus,
            'selectionMenus' => $selectionMenus,
            'selectionRemaining' => $this->selectionRemaining($subscription),
            'assignedCount' => collect($this->scheduleAssignments)->filter()->count(),
            'remainingDays' => $subscription->remainingBillableDays(),
        ])->layout('layouts.private.app', [
            'title' => 'Subscription #'.$subscription->id,
        ]);
    }
}
