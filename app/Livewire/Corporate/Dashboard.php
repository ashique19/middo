<?php

namespace App\Livewire\Corporate;

use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\PackageCheckoutIntent;
use App\Support\PackageGatewayCheckout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    public string $customerName = '';

    public array $metrics = [];

    public array $recentLunches = [];

    public array $upcomingEvents = [];

    public ?array $pendingPaidCheckout = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->customerName = $user->name ?? 'Corporate Partner';

        $this->loadPendingPaidCheckout();
        $this->loadMetrics();
        $this->loadRecentLunches();
        $this->loadUpcomingEvents();
    }

    protected function loadPendingPaidCheckout(): void
    {
        $intent = PackageGatewayCheckout::latestPaidAwaitingOtp((int) Auth::id());
        if (! $intent) {
            $this->pendingPaidCheckout = null;

            return;
        }

        PackageGatewayCheckout::pokeOtp($intent);
        $intent->refresh();

        $this->pendingPaidCheckout = [
            'token' => $intent->payment_token,
            'package_name' => $intent->package?->name ?? 'Package',
            'amount' => (int) $intent->amount,
            'mobile' => $intent->mobile,
            'confirm_url' => $intent->confirmUrl(),
        ];
    }

    /**
     * Aggregates KPI Summary Cards matching the layout top row
     */
    public function loadMetrics(): void
    {
        $userId = Auth::id();
        $today = now('Asia/Dhaka')->toDateString();

        // 1. Count Active Scheduled Orders — shared Order::ACTIVE_STATUSES
        $activeOrdersCount = Order::where('user_id', $userId)
            ->active()
            ->count();

        // 2. Locate Next Scheduled Delivery Run details
        $nextMeal = Order::where('user_id', $userId)
            ->where('delivery_date', '>=', $today)
            ->active()
            ->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc')
            ->first();

        // 3. Asset Registry Custody Count (Boxes currently sitting at client's office)
        $boxesInCustody = MiddoBox::where('held_by_user_id', $userId)
            ->where('asset_status', 'active')
            ->count();

        // 4. Calculate Current Monthly Accumulated Financial Spend
        $dhakaNow = now('Asia/Dhaka');
        $monthlySpend = Order::where('user_id', $userId)
            ->whereYear('delivery_date', $dhakaNow->year)
            ->whereMonth('delivery_date', $dhakaNow->month)
            ->where('order_status', '!=', 'cancelled')
            ->sum('total_amount');

        $this->metrics = [
            'active_orders' => $activeOrdersCount,
            'next_meal_time' => $nextMeal ? Carbon::parse($nextMeal->delivery_date)->format('M d').' - '.$nextMeal->delivery_time : 'None Scheduled',
            'boxes_in_custody' => $boxesInCustody,
            'monthly_spend' => (float) $monthlySpend,
        ];
    }

    public function loadRecentLunches(): void
    {
        // Fetches completed individual tracking rows with loaded dish relationships
        $this->recentLunches = Order::with('menuItem')
            ->where('user_id', Auth::id())
            ->where('delivery_date', '<', now('Asia/Dhaka')->toDateString())
            ->orderBy('delivery_date', 'desc')
            ->take(5)
            ->get()
            ->map(fn (Order $order) => $this->presentOrder($order))
            ->all();
    }

    public function loadUpcomingEvents(): void
    {
        // Fetches individual items in a straight chronological timeline sequence
        $this->upcomingEvents = Order::with('menuItem')
            ->where('user_id', Auth::id())
            ->where('delivery_date', '>=', now('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc')
            ->take(3)
            ->get()
            ->map(fn (Order $order) => $this->presentOrder($order))
            ->all();
    }

    protected function presentOrder(Order $order): array
    {
        $row = $order->toArray();
        $party = $order->partyPayload();
        $row['payment_method'] = $party['payment_method'];
        $row['payment_method_label'] = $party['payment_method_label'];

        return $row;
    }

    #[On('corporate-orders-changed')]
    public function refreshOrders(): void
    {
        $this->loadPendingPaidCheckout();
        $this->loadMetrics();
        $this->loadRecentLunches();
        $this->loadUpcomingEvents();
    }

    public function render()
    {
        $pendingIntent = null;
        if (is_array($this->pendingPaidCheckout) && filled($this->pendingPaidCheckout['token'] ?? null)) {
            $pendingIntent = PackageCheckoutIntent::query()
                ->with('package:id,name')
                ->where('payment_token', $this->pendingPaidCheckout['token'])
                ->first();
        }

        return view('livewire.corporate.dashboard', [
            'pendingIntent' => $pendingIntent,
        ])->layout('layouts.public.app');
    }
}
