<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminController::class, 'loginView'])->name('login');
    Route::post('/login', [AdminController::class, 'login'])->name('login.submit');

    Route::middleware('admin')->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');

        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');

        Route::post('/plans', [AdminController::class, 'storePlan'])->name('plans.store');
        Route::patch('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');

        Route::post('/subscriptions', [AdminController::class, 'saveSubscription'])->name('subscriptions.save');
    });
});
