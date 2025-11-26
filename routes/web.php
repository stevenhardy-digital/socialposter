<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAccountController;

// OAuth routes (need to be web routes for session support)
Route::middleware(['auth:sanctum'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/auth/connect/{platform}', [SocialAccountController::class, 'webConnect'])->name('oauth.connect');
    Route::get('/auth/callback/{platform}', [SocialAccountController::class, 'webCallback'])->name('oauth.callback');
});

// Serve the SPA for all routes (Vue Router will handle routing)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
