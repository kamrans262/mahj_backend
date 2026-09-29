<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', StripeWebhookController::class);

Route::prefix('auth')->group(function (): void {
    Route::post('/register/request-otp', [AuthController::class, 'requestRegistrationOtp'])
        ->middleware('throttle:5,1');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegistrationOtp'])
        ->middleware('throttle:10,1');
    Route::post('/otp/resend', [AuthController::class, 'resendOtp'])
        ->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/forgot-password/request-otp', [AuthController::class, 'requestPasswordResetOtp'])
        ->middleware('throttle:5,1');
    Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyPasswordResetOtp'])
        ->middleware('throttle:10,1');
    Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1');
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/user', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::put('/account/email', [AccountController::class, 'changeEmail']);
    Route::put('/account/password', [AccountController::class, 'changePassword']);
    Route::delete('/account', [AccountController::class, 'destroy']);

    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/start-trial', [SubscriptionController::class, 'startTrial']);
    Route::post('/subscription/confirm-checkout', [SubscriptionController::class, 'confirmCheckout']);
    Route::put('/subscription/plan', [SubscriptionController::class, 'changePlan']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
});
