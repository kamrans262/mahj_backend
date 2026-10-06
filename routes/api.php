<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DirectChatController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MatchChatController;
use App\Http\Controllers\Api\MatchCompletionController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MatchFavoriteController;
use App\Http\Controllers\Api\MatchInvitationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PlayerFavoriteController;
use App\Http\Controllers\Api\SportController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SupportContentController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\UserModerationController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', StripeWebhookController::class);
Route::get('/content/support', [SupportContentController::class, 'support']);
Route::get('/content/legal', [SupportContentController::class, 'legal']);

Route::prefix('auth')->group(function (): void {
    Route::post('/register/request-otp', [AuthController::class, 'requestRegistrationOtp'])
        ->middleware('throttle:5,1');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegistrationOtp'])
        ->middleware('throttle:10,1');
    Route::post('/otp/resend', [AuthController::class, 'resendOtp'])
        ->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/google', [AuthController::class, 'google'])
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
    Route::post('/support-requests', [SupportContentController::class, 'submit']);

    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/start-trial', [SubscriptionController::class, 'startTrial']);
    Route::post('/subscription/confirm-checkout', [SubscriptionController::class, 'confirmCheckout']);
    Route::put('/subscription/plan', [SubscriptionController::class, 'changePlan']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

    Route::get('/sports', [SportController::class, 'index']);
    Route::get('/locations/search', [LocationController::class, 'search'])
        ->middleware('throttle:30,1');

    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/my-matches', [MatchInvitationController::class, 'myMatches']);
    Route::get('/favorites', [MatchFavoriteController::class, 'index']);
    Route::get('/favorite-players', [PlayerFavoriteController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::get('/matches/{match}', [MatchController::class, 'show']);
    Route::get('/matches/{match}/people', [MatchController::class, 'people']);
    Route::put('/matches/{match}/schedule', [MatchController::class, 'updateSchedule']);
    Route::post('/matches/{match}/join', [MatchController::class, 'join']);
    Route::post('/matches/{match}/favorite', [MatchFavoriteController::class, 'store']);
    Route::delete('/matches/{match}/favorite', [MatchFavoriteController::class, 'destroy']);
    Route::get('/users/{user}/favorite', [PlayerFavoriteController::class, 'status']);
    Route::post('/users/{user}/favorite', [PlayerFavoriteController::class, 'store']);
    Route::delete('/users/{user}/favorite', [PlayerFavoriteController::class, 'destroy']);
    Route::post('/matches/{match}/leave', [MatchController::class, 'leave']);
    Route::post('/matches/{match}/cancel', [MatchController::class, 'cancel']);
    Route::get('/matches/{match}/completion', [MatchCompletionController::class, 'show']);
    Route::post('/matches/{match}/complete', [MatchCompletionController::class, 'complete']);
    Route::post('/matches/{match}/scores', [MatchCompletionController::class, 'submitScores']);
    Route::get('/matches/{match}/chat', [MatchChatController::class, 'index']);
    Route::post('/matches/{match}/chat/messages', [MatchChatController::class, 'send']);
    Route::get('/direct-chats', [DirectChatController::class, 'index']);
    Route::post('/direct-chats/{user}', [DirectChatController::class, 'start']);
    Route::get('/direct-chats/{conversation}', [DirectChatController::class, 'show']);
    Route::post('/direct-chats/{conversation}/messages', [DirectChatController::class, 'send']);
    Route::get('/matches/{match}/invite-candidates', [MatchInvitationController::class, 'candidates']);
    Route::post('/matches/{match}/invitations', [MatchInvitationController::class, 'send']);
    Route::post('/invitations/{invitation}/accept', [MatchInvitationController::class, 'accept']);
    Route::post('/invitations/{invitation}/decline', [MatchInvitationController::class, 'decline']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::get('/notification-settings', [NotificationController::class, 'settings']);
    Route::put('/notification-settings', [NotificationController::class, 'updateSettings']);
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    Route::get('/safety', [UserModerationController::class, 'index']);
    Route::get('/users/search', [UserModerationController::class, 'search'])
        ->middleware('throttle:30,1');
    Route::post('/users/{user}/report', [UserModerationController::class, 'report']);
    Route::post('/users/{user}/block', [UserModerationController::class, 'block']);
    Route::delete('/users/{user}/block', [UserModerationController::class, 'unblock']);
});
