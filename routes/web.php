<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [SessionController::class, 'create'])->name('login');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');
});
Route::post('/session', [SessionController::class, 'store'])->name('session.store');
Route::delete('/session', [SessionController::class, 'destroy'])->middleware('auth')->name('session.destroy');

Route::middleware('auth')->group(function () {
    Route::post('/push-subscription', [PushSubscriptionController::class, 'store'])->name('push.store');
    Route::delete('/push-subscription', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
});

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    require __DIR__.'/seller.php';
    require __DIR__.'/seller-scheduling.php';
});
Route::middleware(['auth', 'role:buyer'])->prefix('buyer')->name('buyer.')->group(__DIR__.'/buyer.php');
