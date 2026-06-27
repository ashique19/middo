<?php

namespace App\Livewire\Corporate;

use Livewire\Component;
use App\Models\Order;
use App\Models\MiddoBox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard extends Component
{
    public string $customerName = '';
    public array $metrics = [];
    public array $recentLunches = [];
    public array $upcomingEvents = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->customerName = $user->name ?? 'Corporate Partner';
        
        $this->loadMetrics();
        $this->loadRecentLunches();
        $this->loadUpcomingEvents();
    }

    /**
     * Aggregates KPI Summary Cards matching the layout top row
     */
    public function loadMetrics(): void
    {
        $userId = Auth::id();

        // 1. Count Active Scheduled Orders (Pending or Processing states)
        $activeOrdersCount = Order::where('user_id', $userId)
            ->whereIn('order_status', ['pending', 'processing'])
            ->count();

        // 2. Locate Next Scheduled Delivery Run details
        $nextMeal = Order::where('user_id', $userId)
            ->where('delivery_date', '>=', now()->toDateString())
            ->whereIn('order_status', ['pending', 'processing'])
            ->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc')
            ->first();

        // 3. Asset Registry Custody Count (Boxes currently sitting at client's office)
        $boxesInCustody = MiddoBox::where('held_by_user_id', $userId)
            ->where('asset_status', 'active')
            ->count();

        // 4. Calculate Current Monthly Accumulated Financial Spend
        $monthlySpend = Order::where('user_id', $userId)
            ->whereYear('delivery_date', Carbon::now()->year)
            ->whereMonth('delivery_date', Carbon::now()->month)
            ->where('order_status', '!=', 'cancelled')
            ->sum('total_amount');

        $this->metrics = [
            'active_orders'    => $activeOrdersCount,
            'next_meal_time'   => $nextMeal ? Carbon::parse($nextMeal->delivery_date)->format('M d') . ' - ' . $nextMeal->delivery_time : 'None Scheduled',
            'boxes_in_custody' => $boxesInCustody,
            'monthly_spend'    => number_format($monthlySpend, 0),
        ];
    }

    public function loadRecentLunches(): void
    {
        // Fetches completed individual tracking rows with loaded dish relationships
        $this->recentLunches = Order::with('menuItem')
            ->where('user_id', Auth::id())
            ->where('delivery_date', '<', now()->toDateString())
            // ->where('order_status', 'completed')
            ->orderBy('delivery_date', 'desc')
            ->take(5)
            ->get()
            ->toArray();
    }

    

    public function loadUpcomingEvents(): void
    {
        // Fetches individual items in a straight chronological timeline sequence
        $this->upcomingEvents = Order::with('menuItem')
            ->where('user_id', Auth::id())
            ->where('delivery_date', '>=', now()->setTimezone('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc')
            ->take(3)
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.corporate.dashboard')
            ->layout('layouts.public.app'); // Pairs with your main container application framework
    }
}