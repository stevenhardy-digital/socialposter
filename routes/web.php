<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAccountController;

// OAuth routes (need to be web routes for session support)
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    // Connect route - handle authentication manually to work with both API and web contexts
    Route::post('/auth/connect/{platform}', [SocialAccountController::class, 'webConnect'])->name('oauth.connect');
    
    // Callback route doesn't need auth middleware (user info stored in OAuth state)
    Route::get('/auth/callback/{platform}', [SocialAccountController::class, 'webCallback'])->name('oauth.callback');
});

// Serve the SPA for all routes (Vue Router will handle routing)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
