<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Buyer;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Seller;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api-guest')->group(function () {
    Route::post('/register', [AccountController::class, 'store']);
    Route::post('/tokens', [TokenController::class, 'store']);
});

// Ids have up to 18 digits, so every accepted one fits in the column.
$id = '[0-9]{1,18}';

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->where(['listing' => $id, 'photo' => $id, 'range' => $id, 'showing' => $id, 'window' => $id])
    ->group(function () {
        Route::get('/me', [AccountController::class, 'show']);
        Route::delete('/tokens/current', [TokenController::class, 'destroyCurrent']);
        Route::delete('/tokens', [TokenController::class, 'destroyAll']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read', [NotificationController::class, 'read']);

        Route::middleware('role:buyer')->prefix('buyer')->group(function () {
            Route::get('/availability', [Buyer\AvailabilityController::class, 'index']);
            Route::post('/availability', [Buyer\AvailabilityController::class, 'store']);
            Route::delete('/availability/{range}', [Buyer\AvailabilityController::class, 'destroy']);
            Route::get('/listings', [Buyer\ListingController::class, 'index']);
            Route::get('/showings', [Buyer\ShowingController::class, 'index']);
            Route::post('/showings', [Buyer\ShowingController::class, 'store']);
            Route::post('/showings/{showing}/cancel', [Buyer\ShowingController::class, 'cancel']);
        });

        Route::middleware('role:seller')->prefix('seller')->group(function () {
            Route::get('/listing-options', [Seller\ListingController::class, 'options']);
            Route::get('/listings', [Seller\ListingController::class, 'index']);
            Route::post('/listings', [Seller\ListingController::class, 'store']);
            Route::get('/listings/{listing}', [Seller\ListingController::class, 'show']);
            Route::put('/listings/{listing}', [Seller\ListingController::class, 'update']);
            Route::get('/listings/{listing}/schedule', [Seller\ScheduleController::class, 'show']);
            Route::post('/listings/{listing}/photos', [Seller\PhotoController::class, 'store']);
            Route::put('/listings/{listing}/photos/order', [Seller\PhotoController::class, 'order']);
            Route::patch('/listings/{listing}/photos/{photo}', [Seller\PhotoController::class, 'update']);
            Route::delete('/listings/{listing}/photos/{photo}', [Seller\PhotoController::class, 'destroy']);
            Route::post('/listings/{listing}/windows', [Seller\WindowController::class, 'store']);
            Route::post('/windows/{window}/close', [Seller\WindowController::class, 'close']);
            Route::post('/showings/{showing}/cancel', [Seller\ShowingController::class, 'cancel']);
        });
    });
