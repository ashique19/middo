<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Loading your custom route files
            Route::middleware('web')->group(base_path('routes/web/public.php'));
            Route::prefix('corporate')->middleware('web')->group(base_path('routes/web/corporates.php'));
            Route::prefix('kitchen')->middleware('web')->group(base_path('routes/web/kitchen.php'));
            Route::prefix('admin')->middleware('web')->group(base_path('routes/web/admin.php'));
            Route::prefix('delivery')->middleware('web')->group(base_path('routes/web/deliveryman.php'));
            Route::prefix('operation')->middleware('web')->group(base_path('routes/web/operation.php'));
            Route::prefix('accounts')->middleware('web')->group(base_path('routes/web/accounts.php'));
            Route::prefix('marketing')->middleware('web')->group(base_path('routes/web/marketing.php'));

            // Corporate / kitchen Flutter mobile APIs
            Route::prefix('api')->middleware('api')->group(base_path('routes/api/corporate.php'));
            Route::prefix('api')->middleware('api')->group(base_path('routes/api/kitchen.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Aliases registered for use in your route files
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => CheckPermission::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('kitchen:warn-accept-windows')->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
