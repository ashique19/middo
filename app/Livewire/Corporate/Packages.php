<?php

namespace App\Livewire\Corporate;

use App\Models\MealPackage;
use App\Models\PackageCheckoutIntent;
use App\Models\PackageSubscription;
use App\Support\PackageGatewayCheckout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Packages extends Component
{
    public array $packages = [];

    public array $subscriptions = [];

    public ?array $pendingPaidCheckout = null;

    public function mount(): void
    {
        $this->loadPendingPaidCheckout(pokeOtp: true);
        $this->loadPackages();
        $this->loadSubscriptions();
    }

    #[On('package-subscribed')]
    #[On('corporate-orders-changed')]
    public function refresh(): void
    {
        $this->loadPendingPaidCheckout(pokeOtp: false);
        $this->loadPackages();
        $this->loadSubscriptions();
    }

    protected function loadPendingPaidCheckout(bool $pokeOtp): void
    {
        $intent = PackageGatewayCheckout::latestPaidAwaitingOtp((int) Auth::id());
        if (! $intent) {
            $this->pendingPaidCheckout = null;

            return;
        }

        if ($pokeOtp) {
            PackageGatewayCheckout::pokeOtp($intent);
            $intent->refresh();
        }

        $this->pendingPaidCheckout = [
            'token' => $intent->payment_token,
            'package_name' => $intent->package?->name ?? 'Package',
            'amount' => (int) $intent->amount,
            'mobile' => $intent->mobile,
            'confirm_url' => $intent->confirmUrl(),
        ];
    }

    protected function loadPackages(): void
    {
        $query = MealPackage::query()
            ->published()
            ->withCount('days')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now('Asia/Dhaka')->toDateString());
            })
            ->orderBy('display_order')
            ->orderBy('price_per_day');

        $this->packages = $query->get()->map(function (MealPackage $package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'summary' => $package->summary,
                'price_per_day' => (int) $package->price_per_day,
                'duration_days' => (int) $package->duration_days,
                'start_date' => optional($package->start_date)?->toDateString(),
                'end_date' => optional($package->end_date)?->toDateString(),
                'thumbnail' => $package->thumbnail ? asset($package->thumbnail) : null,
                'days_count' => (int) $package->days_count,
                'sample_days' => [],
            ];
        })->values()->all();
    }

    protected function loadSubscriptions(): void
    {
        $this->subscriptions = PackageSubscription::query()
            ->forUser(Auth::id())
            ->with(['package', 'orders' => fn ($q) => $q->orderBy('delivery_date'), 'selections'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function (PackageSubscription $sub) {
                $pending = $sub->orders->where('order_status', 'pending')->count();
                $total = $sub->orders->where('order_status', '!=', 'cancelled')->count();
                $deliveredish = $sub->orders->whereIn('order_status', ['delivered', 'delivered_and_paid'])->count();

                return [
                    'id' => $sub->id,
                    'name' => $sub->package?->name ?? 'Package',
                    'status' => $sub->status,
                    'schedule_status' => $sub->schedule_status,
                    'price_per_day' => (int) $sub->price_per_day,
                    'billable_days' => (int) $sub->billable_days,
                    'total_amount' => (int) $sub->total_amount,
                    'quantity' => (int) $sub->quantity,
                    'start_date' => $sub->start_date->toDateString(),
                    'end_date' => $sub->end_date->toDateString(),
                    'target_month' => $sub->target_month,
                    'pending_days' => $pending,
                    'active_days' => $total,
                    'completed_days' => $deliveredish,
                ];
            })->values()->all();
    }

    public function openSubscribe(int $packageId): void
    {
        $pending = PackageGatewayCheckout::latestPaidAwaitingOtp((int) Auth::id());
        if ($pending) {
            PackageGatewayCheckout::pokeOtp($pending);
            $this->redirect($pending->confirmUrl());

            return;
        }

        $this->dispatch('open-package-subscribe', packageId: $packageId);
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

        return view('livewire.corporate.packages', [
            'pendingIntent' => $pendingIntent,
        ])->layout('layouts.public.app');
    }
}
