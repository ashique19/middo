<?php

use App\Livewire\Marketing\Companies;
use App\Livewire\Marketing\CompanyShow;
use App\Livewire\Marketing\Dashboard;
use Illuminate\Support\Facades\Route;

// routes/web/marketing.php — ground marketing field portal
Route::middleware(['auth', 'role:ground_marketing'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('marketing.dashboard');
    Route::get('/companies', Companies::class)->name('marketing.companies.index');
    Route::get('/companies/{company}', CompanyShow::class)->name('marketing.companies.show');
});
