<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAccountController;

// OAuth callback routes (need to be web routes for session support)
Route::get('/auth/callback/{platform}', [SocialAccountController::class, 'webCallback'])->name('oauth.callback');

// Serve the SPA for all routes (Vue Router will handle routing)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
