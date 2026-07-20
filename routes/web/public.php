<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CorporateGatewayPrepayController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\EpsPaymentCallbackController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\PublicViewController;
use App\Models\Area;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::controller(PublicViewController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('/about', 'about')->name('about');
    Route::get('/how-it-works/corporates', 'howItWorksCorporates')->name('how-it-works-corporates');
    Route::get('/how-it-works/kitchen', 'howItWorksKitchen')->name('how-it-works-kitchen');
    Route::get('/menu', 'menu')->name('menu');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/privacy', 'privacy')->name('privacy');
    Route::get('/terms', 'terms')->name('terms');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact/submit', 'contactSubmit')->name('contact.submit')->middleware('throttle:1,2');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/kitchen-signup', 'auth.kitchen-register')->name('kitchen.register');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');

Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
    Route::post('/register/send-otp', 'sendSignupOtp')->name('register.send-otp');
    Route::post('/register', 'register');
    Route::post('/kitchen-signup', 'registerKitchen');
    Route::match(['get', 'post'], '/logout', 'logout')->name('logout');
    Route::post('/forgot-password', 'forgotPassword')->name('password.email');
    Route::post('/reset-password', 'resetPassword')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/api/areas/{city_id}', function ($city_id) {
    return Area::where('city_id', $city_id)->get();
});

Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware('auth')
    ->name('dashboard.redirect');
Route::get('/pay/orders/{order}', [OrderPaymentController::class, 'show'])
    ->name('public.order-payment');
Route::post('/pay/orders/{order}', [OrderPaymentController::class, 'confirm'])
    ->name('public.order-payment.confirm');
Route::get('/pay/corporate-prepay/{token}', [CorporateGatewayPrepayController::class, 'show'])
    ->name('corporate.gateway-prepay.show');
Route::post('/pay/corporate-prepay/{token}', [CorporateGatewayPrepayController::class, 'confirm'])
    ->name('corporate.gateway-prepay.confirm');

Route::get('/pay/eps/success/{token}', [EpsPaymentCallbackController::class, 'success'])
    ->name('payments.eps.success');
Route::get('/pay/eps/fail/{token}', [EpsPaymentCallbackController::class, 'fail'])
    ->name('payments.eps.fail');
Route::get('/pay/eps/cancel/{token}', [EpsPaymentCallbackController::class, 'cancel'])
    ->name('payments.eps.cancel');
