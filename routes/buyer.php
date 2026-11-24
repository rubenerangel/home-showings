<?php

use App\Http\Controllers\Buyer\ListingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('buyer/Home'))->name('home');
Route::get('/listings', [ListingController::class, 'index'])->name('listings');
