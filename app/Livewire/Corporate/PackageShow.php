<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use App\Models\PackageSubscription;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageRefund;
use App\Support\PackageSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PackageShow extends Component
{
    public int $subscriptionId;

    public array $subscription = [];

    public array $days = [];

    public array $selections = [];

    public string $errorMessage = '';

    public string $successMessage = '';

    public function mount(int $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
        $this->loadSubscription();
    }

    protected function loadSubscription(): void
    {
        $sub = PackageSubscription::query()
            ->forUser(Auth::id())
            ->with(['package', 'orders.menuItem', 'selections.menuItem'])
            ->findOrFail($this->subscriptionId);

        $this->subscription = [
            'id' => $sub->id,
            'name' => $sub->package?->name ?? 'Package',
            'status' => $sub->status,
            'schedule_status' => $sub->schedule_status,
            'price_per_day' => (int) $sub->price_per_day,
            'billable_days' => (int) $sub->billable_days,
            'total_amount' => (int) $sub->total_amount,
            'amount_paid' => (int) $sub->amount_paid,
            'quantity' => (int) $sub->quantity,
            'start_date' => $sub->start_date->toDateString(),
            'end_date' => $sub->end_date->toDateString(),
            'target_month' => $sub->target_month,
            'omitted_weekdays' => $sub->omitted_weekdays ?? [],
            'delivery_time' => $sub->delivery_time,
            'address' => $sub->address,
            'receiver_name' => $sub->receiver_name,
            'is_awaiting_schedule' => $sub->isAwaitingSchedule(),
        ];

        $this->selections = $sub->selections->map(fn ($sel) => [
            'menu_item_id' => (int) $sel->menu_item_id,
            'name' => $sel->menuItem?->name ?? 'Menu',
            'day_count' => (int) $sel->day_count,
            'thumbnail' => $sel->menuItem?->thumbnail ? asset($sel->menuItem->thumbnail) : null,
        ])->values()->all();

        $refunds = PackageRefund::packageDayRefundAllocations($sub);

        $this->days = $sub->orders
            ->sortBy('delivery_date')
            ->values()
            ->map(function (Order $order) use ($refunds) {
                return [
                    'id' => $order->id,
                    'date' => $order->delivery_date->toDateString(),
                    'weekday' => $order->delivery_date->format('D'),
                    'menu_name' => $order->menuItem?->name ?? 'Meal',
                    'thumbnail' => $order->menuItem?->thumbnail ? asset($order->menuItem->thumbnail) : null,
                    'quantity' => (int) $order->quantity,
                    'total_amount' => (int) $order->total_amount,
                    'amount_paid' => (int) $order->amount_paid,
                    'refund_amount' => $refunds[(int) $order->id] ?? PackageRefund::orderRefundAmount($order),
                    'order_status' => $order->order_status,
                    'can_skip' => $order->order_status === 'pending' && OrderCutoff::allowsModification($order),
                ];
            })
            ->all();
    }

    public function skipDay(int $orderId): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $order = Order::query()
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->where('package_subscription_id', $this->subscriptionId)
            ->first();

        if (! $order) {
            $this->errorMessage = 'Day not found.';

            return;
        }

        try {
            $result = app(PackageSubscriptionService::class)->skipDay(Auth::user(), $order);
            $refund = (int) $result['refunded_amount'];
            $this->successMessage = 'Day skipped. ৳'.number_format($refund).' credited to your Middo Balance.';
            $this->dispatch('corporate-orders-changed');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }

        $this->loadSubscription();
    }

    public function omittedLabels(): string
    {
        $labels = PackageBilling::WEEKDAY_LABELS;
        $omitted = $this->subscription['omitted_weekdays'] ?? [];

        if ($omitted === []) {
            return 'none';
        }

        return collect($omitted)
            ->map(fn ($d) => $labels[(int) $d] ?? (string) $d)
            ->implode(', ');
    }

    public function render()
    {
        return view('livewire.corporate.package-show')
            ->layout('layouts.public.app');
    }
}
