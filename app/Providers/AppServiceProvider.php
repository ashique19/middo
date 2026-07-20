<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Support\Payments\EpsPaymentGateway;
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
            $driver = config('payments.driver', 'eps');

            return match ($driver) {
                'pseudo' => new PseudoPaymentGateway,
                default => new EpsPaymentGateway,
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
