<?php

use App\Http\Controllers\Buyer\AvailabilityController;
use App\Http\Controllers\Buyer\ListingController;
use App\Http\Controllers\Buyer\ShowingController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('buyer/Home'))->name('home');
Route::get('/listings', [ListingController::class, 'index'])->name('listings');

Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');
Route::post('/availability', [AvailabilityController::class, 'store'])->name('availability.store');
Route::delete('/availability/{range}', [AvailabilityController::class, 'destroy'])->where('range', '[0-9]{1,18}')->name('availability.destroy');

Route::post('/showings', [ShowingController::class, 'store'])->name('showings.store');
Route::post('/showings/{showing}/cancel', [ShowingController::class, 'cancel'])->where('showing', '[0-9]{1,18}')->name('showings.cancel');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');
