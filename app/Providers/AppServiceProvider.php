<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Support\Payments\PseudoPaymentGateway;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function () {
            // Swap this binding for a real SSLCommerz/bKash driver when ready.
            $driver = config('payments.driver', 'pseudo');

            return match ($driver) {
                default => new PseudoPaymentGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        if ($this->app->environment('production') || filled(env('CODESPACE_NAME'))) {
            URL::forceScheme('https');
        }
    }
}
