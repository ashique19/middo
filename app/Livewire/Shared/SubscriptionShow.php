<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use App\Models\PackageDayCancelRequest;
use App\Models\PackageSubscription;
use App\Models\PackageSubscriptionEvent;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageDayCancelRequestService;
use App\Support\PackageRefund;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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

    public bool $showSwapModal = false;

    public ?int $swapOrderId = null;

    public ?int $swapMenuItemId = null;

    public bool $showCancelModal = false;

    public ?int $cancelOrderId = null;

    public ?string $cancelDate = null;

    public ?int $cancelMenuItemId = null;

    public string $cancelReason = '';

    public bool $showReactivateModal = false;

    public ?int $reactivateOrderId = null;

    public ?int $reviewRequestId = null;

    public string $reviewOpsNote = '';

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
            'user.role',
            'package',
            'area.city',
            'selections.menuItem',
            'orders' => fn ($q) => $q->with(['menuItem', 'orderGroup'])->orderBy('delivery_date'),
            'pendingCancelRequests.requestedBy',
        ])->findOrFail($this->subscriptionId);
    }

    protected function resetScheduleAssignments(?PackageSubscription $model = null): void
    {
        $model ??= $this->subscription();

        $cancelledOpen = $model->orders
            ->where('order_status', 'cancelled')
            ->filter(fn ($order) => OrderCutoff::deliveryDateStillOpen($order));

        $next = [];

        if ($model->canReceiveScheduleAssignments() || $cancelledOpen->isNotEmpty()) {
            $month = (string) ($model->target_month ?: $model->start_date->format('Y-m'));
            $dates = PackageBilling::availableDatesInMonth($month, $model->omitted_weekdays ?? []);
            $confirmedDates = $model->orders
                ->where('order_status', '!=', 'cancelled')
                ->map(fn ($order) => $order->delivery_date->toDateString())
                ->all();

            $openDates = [];
            $cancelledDates = [];

            foreach ($dates as $date) {
                if (in_array($date, $confirmedDates, true)) {
                    continue;
                }

                $cancelledForDate = $cancelledOpen->first(
                    fn ($order) => $order->delivery_date->toDateString() === $date
                );

                if ($cancelledForDate) {
                    // Placeholder key only — menu display comes from cancelledOrdersByDate.
                    // Do not store menu_item_id here or it is counted as a draft assignment.
                    $cancelledDates[$date] = null;
                } elseif ($model->canReceiveScheduleAssignments()) {
                    $openDates[$date] = null;
                }
            }

            // Open unconfirmed days first; cancelled days (re-activate) at the end.
            $next = $openDates + $cancelledDates;
        }

        // Drop stale Livewire keys so reactivated dates leave the pending list.
        foreach (array_keys($this->scheduleAssignments) as $existingDate) {
            if (! array_key_exists($existingDate, $next)) {
                unset($this->scheduleAssignments[$existingDate]);
            }
        }

        foreach ($next as $date => $menuId) {
            $this->scheduleAssignments[$date] = $menuId;
        }
    }

    /**
     * @return array<string, Order>
     */
    public function cancelledOrdersByDate(PackageSubscription $subscription): array
    {
        $map = [];
        foreach ($subscription->orders->where('order_status', 'cancelled') as $order) {
            if (! OrderCutoff::deliveryDateStillOpen($order)) {
                continue;
            }
            $map[$order->delivery_date->toDateString()] = $order;
        }

        return $map;
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

    public function saveScheduleDate(string $date): void
    {
        abort_unless($this->canManage, 403);
        $this->errorMessage = null;
        $this->statusMessage = null;

        if (! array_key_exists($date, $this->scheduleAssignments)) {
            $this->errorMessage = 'That date is not available to confirm.';

            return;
        }

        if (isset($this->cancelledOrdersByDate($this->subscription())[$date])) {
            $this->errorMessage = 'That day was cancelled. Use Re-activate instead of Save.';

            return;
        }

        $menuItemId = $this->scheduleAssignments[$date] ?? null;
        if (! filled($menuItemId)) {
            $this->errorMessage = 'Pick a menu before saving '.$date.'.';

            return;
        }

        try {
            $result = app(PackageSubscriptionService::class)->assignSchedule(
                Auth::user(),
                $this->subscription(),
                [['date' => $date, 'menu_item_id' => (int) $menuItemId]]
            );
            $remaining = $result['subscription']->remainingBillableDays();
            $this->statusMessage = $remaining > 0
                ? 'Confirmed '.$date.'. '.$remaining.' day(s) still unconfirmed.'
                : 'Confirmed '.$date.'. Package schedule is complete.';
            $this->resetScheduleAssignments($result['subscription']);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openSwapModal(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        $order = Order::query()->findOrFail($orderId);
        abort_unless((int) $order->package_subscription_id === $this->subscriptionId, 404);

        $this->swapOrderId = $orderId;
        $this->swapMenuItemId = (int) $order->menu_item_id;
        $this->showSwapModal = true;
        $this->errorMessage = null;
    }

    public function closeSwapModal(): void
    {
        $this->showSwapModal = false;
        $this->swapOrderId = null;
        $this->swapMenuItemId = null;
    }

    public function confirmSwap(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->swapOrderId || ! $this->swapMenuItemId) {
            $this->errorMessage = 'Pick a menu item to swap to.';

            return;
        }

        try {
            $order = Order::query()->findOrFail($this->swapOrderId);
            app(PackageSubscriptionService::class)->swapDayMenu(
                Auth::user(),
                $order,
                (int) $this->swapMenuItemId
            );
            $this->statusMessage = 'Swapped menu for order #'.$this->swapOrderId.'.';
            $this->errorMessage = null;
            $this->closeSwapModal();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function openCancelModal(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        $order = Order::query()->findOrFail($orderId);
        abort_unless((int) $order->package_subscription_id === $this->subscriptionId, 404);

        $this->cancelOrderId = $orderId;
        $this->cancelDate = null;
        $this->cancelMenuItemId = null;
        $this->cancelReason = '';
        $this->showCancelModal = true;
        $this->errorMessage = null;
    }

    public function openCancelUnscheduledModal(string $date): void
    {
        abort_unless($this->canManage, 403);

        if (! array_key_exists($date, $this->scheduleAssignments)) {
            $this->errorMessage = 'That date is not available to cancel.';

            return;
        }

        $draftMenu = $this->scheduleAssignments[$date] ?? null;
        $this->cancelOrderId = null;
        $this->cancelDate = $date;
        $this->cancelMenuItemId = filled($draftMenu) ? (int) $draftMenu : null;
        $this->cancelReason = '';
        $this->showCancelModal = true;
        $this->errorMessage = null;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->cancelOrderId = null;
        $this->cancelDate = null;
        $this->cancelMenuItemId = null;
        $this->cancelReason = '';
    }

    public function confirmCancelAndRefund(): void
    {
        abort_unless($this->canManage, 403);

        $reason = trim($this->cancelReason);
        if ($reason === '') {
            $this->errorMessage = 'Enter a cancellation reason.';

            return;
        }

        try {
            if ($this->cancelOrderId) {
                $order = Order::query()->findOrFail($this->cancelOrderId);
                $result = app(PackageSubscriptionService::class)->skipDayAsStaff(
                    Auth::user(),
                    $order,
                    $reason
                );
                $this->statusMessage = 'Cancelled package day order #'.$this->cancelOrderId.' and refunded ৳'.number_format($result['refunded_amount']).' to the corporate wallet.';
            } elseif ($this->cancelDate) {
                if (! $this->cancelMenuItemId) {
                    $this->errorMessage = 'Pick which prepaid menu day to cancel.';

                    return;
                }

                $result = app(PackageSubscriptionService::class)->cancelUnscheduledDay(
                    Auth::user(),
                    $this->subscription(),
                    $this->cancelDate,
                    (int) $this->cancelMenuItemId,
                    $reason
                );
                $this->statusMessage = 'Cancelled unconfirmed '.$this->cancelDate.' and refunded ৳'.number_format($result['refunded_amount']).' to the corporate wallet.';
            } else {
                $this->errorMessage = 'Pick a delivery day to cancel.';

                return;
            }

            $this->errorMessage = null;
            $this->closeCancelModal();
            $this->resetScheduleAssignments();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function approveCancelRequest(int $requestId): void
    {
        abort_unless($this->canManage, 403);

        $request = PackageDayCancelRequest::query()
            ->where('id', $requestId)
            ->where('package_subscription_id', $this->subscriptionId)
            ->firstOrFail();

        try {
            $result = app(PackageDayCancelRequestService::class)->approve(
                Auth::user(),
                $request,
                $this->reviewRequestId === $requestId ? $this->reviewOpsNote : null
            );
            $this->statusMessage = 'Approved cancel request — refunded ৳'.number_format($result['refunded_amount']).' to the corporate wallet.';
            $this->errorMessage = null;
            $this->reviewRequestId = null;
            $this->reviewOpsNote = '';
            $this->resetScheduleAssignments();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function rejectCancelRequest(int $requestId): void
    {
        abort_unless($this->canManage, 403);

        $request = PackageDayCancelRequest::query()
            ->where('id', $requestId)
            ->where('package_subscription_id', $this->subscriptionId)
            ->firstOrFail();

        try {
            app(PackageDayCancelRequestService::class)->reject(
                Auth::user(),
                $request,
                $this->reviewRequestId === $requestId ? $this->reviewOpsNote : null
            );
            $this->statusMessage = 'Rejected cancel request for order #'.$request->order_id.'.';
            $this->errorMessage = null;
            $this->reviewRequestId = null;
            $this->reviewOpsNote = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function toggleReviewRequest(int $requestId): void
    {
        abort_unless($this->canManage, 403);

        if ($this->reviewRequestId === $requestId) {
            $this->reviewRequestId = null;
            $this->reviewOpsNote = '';

            return;
        }

        $this->reviewRequestId = $requestId;
        $this->reviewOpsNote = '';
    }

    public function unconfirmOrder(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        try {
            $order = Order::query()->findOrFail($orderId);
            app(PackageSubscriptionService::class)->unconfirmDay(Auth::user(), $order);
            $this->statusMessage = 'Unconfirmed order #'.$orderId.' — day returned to the unconfirmed list.';
            $this->errorMessage = null;
            $this->resetScheduleAssignments();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function openReactivateModal(int $orderId): void
    {
        abort_unless($this->canManage, 403);

        $order = Order::query()->findOrFail($orderId);
        abort_unless((int) $order->package_subscription_id === $this->subscriptionId, 404);
        abort_unless($order->order_status === 'cancelled', 404);

        $this->reactivateOrderId = $orderId;
        $this->showReactivateModal = true;
        $this->errorMessage = null;
    }

    public function closeReactivateModal(): void
    {
        $this->showReactivateModal = false;
        $this->reactivateOrderId = null;
    }

    public function confirmReactivate(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->reactivateOrderId) {
            $this->errorMessage = 'Pick a cancelled day to re-activate.';

            return;
        }

        try {
            $order = Order::query()->findOrFail($this->reactivateOrderId);
            $actor = Auth::user();
            $actor?->loadMissing('role');
            $result = app(PackageSubscriptionService::class)->reactivateDay($actor, $order);
            $menuName = $result['order']->menuItem?->name ?? 'menu';
            $this->statusMessage = 'Re-activated order #'.$this->reactivateOrderId.' ('.$menuName.') — moved back to delivery days. Debited Tk '.number_format($result['debited_amount']).'.';
            $this->errorMessage = null;
            $this->closeReactivateModal();
            $this->resetScheduleAssignments($this->subscription());
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

    public function corporateShowRoute(?PackageSubscription $subscription = null): ?string
    {
        $subscription ??= $this->subscription();
        $user = $subscription->user;

        if (! $user || $user->role?->name !== 'corporate') {
            return null;
        }

        $role = Auth::user()?->role?->name;
        $routeName = $role === 'admin' ? 'admin.corporates.show' : 'operation.corporates.show';

        return Route::has($routeName)
            ? route($routeName, $user)
            : null;
    }

    public function orderRefundAmount(Order $order): int
    {
        return PackageRefund::orderRefundAmount($order);
    }

    public function estimatedUnscheduledRefund(?int $menuItemId = null): int
    {
        $menuItemId ??= $this->cancelMenuItemId;
        if (! $menuItemId) {
            return 0;
        }

        $subscription = $this->subscription();
        $selection = $subscription->selections->firstWhere('menu_item_id', (int) $menuItemId);
        if (! $selection) {
            return 0;
        }

        $qty = max(1, (int) $subscription->quantity);
        $dayFood = (int) $selection->unit_price * $qty;
        $days = max(1, (int) $subscription->billable_days);
        $chargeShare = (int) floor(((int) ($subscription->charges_amount ?? 0)) / $days);
        $discountShare = (int) floor(((int) ($subscription->discount_amount ?? 0)) / $days);

        return max(0, $dayFood + $chargeShare - $discountShare);
    }

    public function selectionRemaining(PackageSubscription $subscription): array
    {
        $cancelledDates = array_keys($this->cancelledOrdersByDate($subscription));
        $draftAssigned = collect($this->scheduleAssignments)
            ->reject(fn ($id, $date) => in_array((string) $date, $cancelledDates, true))
            ->filter()
            ->countBy(fn ($id) => (int) $id);

        $remaining = $subscription->remainingSelectionCounts();

        return $subscription->selections->map(function ($sel) use ($draftAssigned, $remaining) {
            $menuId = (int) $sel->menu_item_id;
            $dayCount = (int) $sel->day_count;
            $left = (int) ($remaining[$menuId] ?? 0);
            $draft = (int) ($draftAssigned[$menuId] ?? 0);
            $confirmed = max(0, $dayCount - $left);

            return [
                'menu_item_id' => $menuId,
                'name' => $sel->menuItem?->name ?? 'Menu',
                'unit_price' => (int) $sel->unit_price,
                'day_count' => $dayCount,
                // Confirmed only — drafts must not push assigned above day_count (e.g. 3/2).
                'assigned' => $confirmed,
                'remaining' => max(0, $left - $draft),
            ];
        })->values()->all();
    }

    /**
     * Menus still available to pick for an unconfirmed date (full quotas omitted).
     *
     * @return list<\App\Models\MenuItem>
     */
    public function availableMenusForDate(PackageSubscription $subscription, string $date): array
    {
        $current = $this->scheduleAssignments[$date] ?? null;
        $currentId = filled($current) ? (int) $current : null;

        $remainingByMenu = collect($this->selectionRemaining($subscription))
            ->keyBy('menu_item_id');

        return $subscription->selections
            ->pluck('menuItem')
            ->filter()
            ->filter(function ($menu) use ($remainingByMenu, $currentId) {
                $menuId = (int) $menu->id;
                if ($currentId !== null && $menuId === $currentId) {
                    return true;
                }

                return (int) ($remainingByMenu[$menuId]['remaining'] ?? 0) > 0;
            })
            ->values()
            ->all();
    }

    public function render()
    {
        $subscription = $this->subscription();
        $selectionMenus = $subscription->selections->pluck('menuItem')->filter()->values();
        $selectionRemaining = $this->selectionRemaining($subscription);
        $daySummary = $subscription->daySummary();
        $swapOrder = $this->swapOrderId
            ? $subscription->orders->firstWhere('id', $this->swapOrderId)
            : null;
        $cancelOrder = $this->cancelOrderId
            ? $subscription->orders->firstWhere('id', $this->cancelOrderId)
            : null;
        $reactivateOrder = $this->reactivateOrderId
            ? $subscription->orders->firstWhere('id', $this->reactivateOrderId)
            : null;

        $auditEvents = PackageSubscriptionEvent::query()
            ->where('package_subscription_id', $subscription->id)
            ->with('createdBy')
            ->latest('id')
            ->limit(100)
            ->get();

        $pendingCancelRequests = $subscription->pendingCancelRequests->keyBy('order_id');

        $menusByDate = [];
        foreach (array_keys($this->scheduleAssignments) as $date) {
            $menusByDate[$date] = $this->availableMenusForDate($subscription, (string) $date);
        }

        return view('livewire.shared.subscriptions.show', [
            'subscription' => $subscription,
            'menuItems' => $selectionMenus,
            'selectionMenus' => $selectionMenus,
            'selectionRemaining' => $selectionRemaining,
            'menusByDate' => $menusByDate,
            'assignedCount' => collect($this->scheduleAssignments)->filter()->count(),
            'remainingDays' => $subscription->remainingBillableDays(),
            'daySummary' => $daySummary,
            'auditEvents' => $auditEvents,
            'swapOrder' => $swapOrder,
            'cancelOrder' => $cancelOrder,
            'reactivateOrder' => $reactivateOrder,
            'cancelledOrdersByDate' => $this->cancelledOrdersByDate($subscription),
            'pendingCancelRequests' => $pendingCancelRequests,
        ])->layout('layouts.private.app', [
            'title' => 'Subscription #'.$subscription->id,
        ]);
    }
}
