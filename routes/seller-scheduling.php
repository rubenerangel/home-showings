<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Seller\ShowingController;
use App\Http\Controllers\Seller\WindowController;
use Illuminate\Support\Facades\Route;

Route::post('/listings/{listing}/windows', [WindowController::class, 'store'])->where('listing', '[0-9]{1,18}')->name('windows.store');
Route::post('/windows/{window}/close', [WindowController::class, 'close'])->where('window', '[0-9]{1,18}')->name('windows.close');
Route::post('/showings/{showing}/cancel', [ShowingController::class, 'cancel'])->where('showing', '[0-9]{1,18}')->name('showings.cancel');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');
