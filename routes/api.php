<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandGuidelineController;
use App\Http\Controllers\ContentGenerationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::middleware('throttle:user-info')->get('user', [AuthController::class, 'user']);
    });
});

// Protected API routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Social accounts routes
    Route::prefix('social-accounts')->group(function () {
        Route::get('/', [SocialAccountController::class, 'index']);
        Route::delete('{socialAccount}', [SocialAccountController::class, 'disconnect']);
        Route::post('{socialAccount}/refresh', [SocialAccountController::class, 'refreshToken']);
        Route::post('{socialAccount}/webhooks/subscribe', [SocialAccountController::class, 'subscribeToWebhooks']);
        Route::get('{socialAccount}/info', [SocialAccountController::class, 'getAccountInfo']);
    });
    
    // Brand guidelines routes
    Route::prefix('brand-guidelines')->group(function () {
        Route::get('/', [BrandGuidelineController::class, 'index']);
        Route::get('social-account/{socialAccount}', [BrandGuidelineController::class, 'show']);
        Route::post('social-account/{socialAccount}', [BrandGuidelineController::class, 'store']);
    });
    
    // Content generation routes
    Route::prefix('content-generation')->group(function () {
        Route::post('monthly', [ContentGenerationController::class, 'generateMonthly']);
        Route::get('status', [ContentGenerationController::class, 'getStatus']);
        Route::post('account/{account}', [ContentGenerationController::class, 'generateForAccount']);
        Route::post('account/{account}/single', [ContentGenerationController::class, 'generateSingle']);
    });
    
    // Posts routes
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'index']);
        Route::post('/', [PostController::class, 'store']);
        Route::post('create-and-publish', [PostController::class, 'createAndPublish']);
        Route::get('calendar', [PostController::class, 'getCalendarPosts']);
        Route::get('status/{status}', [PostController::class, 'getByStatus']);
        Route::get('{post}', [PostController::class, 'show']);
        Route::put('{post}', [PostController::class, 'update']);
        Route::put('{post}/schedule', [PostController::class, 'updateSchedule']);
        Route::post('{post}/publish', [PostController::class, 'publish']);
        Route::post('{post}/mark-published', [PostController::class, 'markAsPublished']);
        Route::delete('{post}', [PostController::class, 'destroy']);
        Route::post('{post}/approve', [PostController::class, 'approve']);
        Route::post('{post}/reject', [PostController::class, 'reject']);
    });
    
    // Analytics routes
    Route::prefix('analytics')->group(function () {
        Route::get('/', [\App\Http\Controllers\AnalyticsController::class, 'getAnalytics']);
        Route::get('summary', [\App\Http\Controllers\AnalyticsController::class, 'getAnalyticsSummary']);
        Route::get('post/{post}', [\App\Http\Controllers\AnalyticsController::class, 'getPostAnalytics']);
        Route::post('post/{post}/collect', [\App\Http\Controllers\AnalyticsController::class, 'collectMetrics']);
    });

    // System monitoring routes
    Route::prefix('system')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\SystemController::class, 'dashboard']);
        Route::get('health', [\App\Http\Controllers\SystemController::class, 'healthCheck']);
        Route::get('status', [\App\Http\Controllers\SystemController::class, 'status']);
        Route::get('performance', [\App\Http\Controllers\SystemController::class, 'getPerformanceMetrics']);
        Route::post('test-workflow', [\App\Http\Controllers\SystemController::class, 'testWorkflow']);
    });
});

// Webhook routes (public, no authentication required)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // Facebook webhooks
    Route::get('facebook', [WebhookController::class, 'verifyFacebookWebhook'])->name('facebook.verify');
    Route::post('facebook', [WebhookController::class, 'handleFacebookWebhook'])->name('facebook.handle');
    
    // Instagram webhooks (uses Facebook system)
    Route::get('instagram', [WebhookController::class, 'verifyInstagramWebhook'])->name('instagram.verify');
    Route::post('instagram', [WebhookController::class, 'handleInstagramWebhook'])->name('instagram.handle');
    
    // LinkedIn webhooks (placeholder)
    Route::get('linkedin', [WebhookController::class, 'verifyLinkedInWebhook'])->name('linkedin.verify');
    Route::post('linkedin', [WebhookController::class, 'handleLinkedInWebhook'])->name('linkedin.handle');
    
    // Development/testing endpoints
    Route::post('test/{platform}', [WebhookController::class, 'testWebhook'])->name('test');
    Route::get('status', [WebhookController::class, 'getWebhookStatus'])->name('status');
});