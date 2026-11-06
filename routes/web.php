<?php

use App\Http\Controllers\AdminCommunityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminInvitationController;
use App\Http\Controllers\AdminMatchController;
use App\Http\Controllers\AdminSportController;
use App\Http\Controllers\AdminSupportContentController;
use App\Http\Controllers\PublicLegalController;
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('public.home');
Route::get('/contact-us', [PublicSiteController::class, 'contact'])->name('public.contact');
Route::post('/contact-us', [PublicSiteController::class, 'submitContact'])
    ->middleware('throttle:5,10')->name('public.contact.submit');

// Public store-review and account-management pages; no admin sign-in required.
Route::get('/terms-and-conditions', [PublicLegalController::class, 'show'])
    ->defaults('slug', 'terms-and-conditions')->name('public.terms');
Route::get('/privacy-policy', [PublicLegalController::class, 'show'])
    ->defaults('slug', 'privacy-policy')->name('public.privacy');
Route::get('/account-deletion', [PublicLegalController::class, 'show'])
    ->defaults('slug', 'account-deletion')->name('public.account-deletion');
Route::post('/account-deletion', [PublicLegalController::class, 'requestAccountDeletion'])
    ->middleware('throttle:3,10')->name('public.account-deletion.request');

Route::view('/billing/success', 'billing.success')->name('billing.success');
Route::view('/billing/cancel', 'billing.cancel')->name('billing.cancel');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminController::class, 'loginView'])->name('login');
    Route::post('/login', [AdminController::class, 'login'])->name('login.submit');

    Route::middleware('admin')->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');

        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/{user}', [AdminController::class, 'user'])->name('users.show');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');

        Route::get('/sports', [AdminSportController::class, 'index'])->name('sports');
        Route::post('/sports', [AdminSportController::class, 'store'])->name('sports.store');
        Route::patch('/sports/{sport}', [AdminSportController::class, 'update'])->name('sports.update');

        Route::get('/matches', [AdminMatchController::class, 'index'])->name('matches');
        Route::get('/completed-matches', [AdminMatchController::class, 'completed'])->name('matches.completed');
        Route::get('/invitations', [AdminInvitationController::class, 'index'])->name('invitations');
        Route::get('/matches/{match}', [AdminMatchController::class, 'show'])->name('matches.show');
        Route::patch('/matches/{match}', [AdminMatchController::class, 'update'])->name('matches.update');
        Route::delete('/matches/{match}', [AdminMatchController::class, 'destroy'])->name('matches.delete');

        Route::get('/chats', [AdminCommunityController::class, 'chats'])->name('chats');
        Route::get('/notifications', [AdminCommunityController::class, 'notifications'])->name('notifications');
        Route::get('/reports', [AdminCommunityController::class, 'reports'])->name('reports');
        Route::patch('/reports/{report}', [AdminCommunityController::class, 'updateReport'])->name('reports.update');
        Route::get('/blocks', [AdminCommunityController::class, 'blocks'])->name('blocks');
        Route::get('/support-requests', [AdminSupportContentController::class, 'supportRequests'])->name('support.requests');
        Route::patch('/support-requests/{supportRequest}', [AdminSupportContentController::class, 'updateSupportRequest'])->name('support.requests.update');
        Route::get('/content', [AdminSupportContentController::class, 'content'])->name('content');
        Route::post('/content/faqs', [AdminSupportContentController::class, 'storeFaq'])->name('content.faqs.store');
        Route::patch('/content/faqs/{faq}', [AdminSupportContentController::class, 'updateFaq'])->name('content.faqs.update');
        Route::delete('/content/faqs/{faq}', [AdminSupportContentController::class, 'deleteFaq'])->name('content.faqs.delete');
        Route::patch('/content/pages/{page}/preview', [AdminSupportContentController::class, 'previewPage'])->name('content.pages.preview');
        Route::patch('/content/pages/{page}', [AdminSupportContentController::class, 'updatePage'])->name('content.pages.update');
        Route::patch('/content/support-settings', [AdminSupportContentController::class, 'updateSupportSettings'])->name('content.support.update');

        Route::get('/subscriptions', [AdminController::class, 'subscriptions'])->name('subscriptions');
        Route::get('/subscriptions/users/{user}', [AdminController::class, 'subscriptionUser'])
            ->name('subscriptions.user');
        Route::post('/subscriptions', [AdminController::class, 'saveSubscription'])->name('subscriptions.save');

        Route::post('/plans', [AdminController::class, 'storePlan'])->name('plans.store');
        Route::patch('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');
    });
});
