<?php

use Illuminate\Support\Facades\Route;

// Redirect root to your public landing page
Route::get('/', function () {
    return redirect()->route('home'); // Assuming you name your route in public.php
});

// Any other global routes that don't fit into roles