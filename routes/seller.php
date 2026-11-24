<?php

use App\Http\Controllers\Seller\ListingController;
use App\Http\Controllers\Seller\PhotoController;
use App\Http\Controllers\Seller\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ListingController::class, 'index'])->name('home');

// Ids have up to 18 digits, so every accepted one fits in the column.
Route::resource('listings', ListingController::class)
    ->only(['create', 'store', 'edit', 'update'])
    ->where(['listing' => '[0-9]{1,18}']);
Route::resource('listings.photos', PhotoController::class)
    ->only(['store', 'update', 'destroy'])
    ->where(['listing' => '[0-9]{1,18}', 'photo' => '[0-9]{1,18}']);
Route::get('/listings/{listing}/schedule', [ScheduleController::class, 'show'])
    ->where('listing', '[0-9]{1,18}')
    ->name('listings.schedule');
