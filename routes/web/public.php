<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PublicViewController;
use App\Http\Controllers\DashboardRedirectController;
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
    Route::post('/register', 'register');
    Route::post('/kitchen-signup', 'registerKitchen');
    Route::post('/logout', 'logout')->name('logout');
    Route::post('/forgot-password', 'forgotPassword')->name('password.email');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/api/areas/{city_id}', function ($city_id) {
    return Area::where('city_id', $city_id)->get();
});



Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard.redirect');