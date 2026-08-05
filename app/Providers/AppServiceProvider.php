<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLog;
use App\Observers\OrderObserver;
use App\Observers\UserObserver;
use App\Support\Payments\EpsPaymentGateway;
use App\Support\Payments\PseudoPaymentGateway;
use App\Support\UserAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
        User::observe(UserObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            /** @var User $user */
            $user = $event->user;

            // Inactive accounts are logged as login_blocked in AuthController after logout.
            if ($user->status !== 'active') {
                return;
            }

            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN,
                performedBy: $user->id,
                metadata: [
                    'guard' => $event->guard,
                    'remember' => (bool) $event->remember,
                ],
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            // Skip logout noise from the inactive-login soft-fail path.
            if ($event->user->status !== 'active') {
                return;
            }

            UserAudit::record(
                user: $event->user,
                event: UserLog::EVENT_LOGOUT,
                performedBy: $event->user->id,
                metadata: [
                    'guard' => $event->guard,
                ],
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $user = $event->user instanceof User ? $event->user : null;

            UserAudit::record(
                user: $user,
                event: UserLog::EVENT_LOGIN_FAILED,
                performedBy: $user?->id,
                metadata: [
                    'guard' => $event->guard,
                    'mobile' => $event->credentials['mobile'] ?? null,
                ],
            );
        });

        $forceHttpsByDefault = $this->app->environment('production') || filled(env('CODESPACE_NAME'));
        if (filter_var(env('FORCE_HTTPS', $forceHttpsByDefault), FILTER_VALIDATE_BOOLEAN)) {
            URL::forceScheme('https');
        }
    }
}
