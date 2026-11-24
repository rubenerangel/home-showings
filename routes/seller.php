<?php

use App\Http\Controllers\Seller\ListingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ListingController::class, 'index'])->name('home');

// Up to 18 digits, so every accepted id fits in the column.
Route::resource('listings', ListingController::class)
    ->only(['create', 'store', 'edit', 'update'])
    ->where(['listing' => '[0-9]{1,18}']);
