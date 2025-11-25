<?php

use Illuminate\Support\Facades\Route;

// Serve the SPA for all routes (Vue Router will handle routing)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
