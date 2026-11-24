<?php

use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'create'])->name('picker');
Route::post('/session', [SessionController::class, 'store'])->name('session.store');
Route::delete('/session', [SessionController::class, 'destroy'])->middleware('auth')->name('session.destroy');

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(__DIR__.'/seller.php');
Route::middleware(['auth', 'role:buyer'])->prefix('buyer')->name('buyer.')->group(__DIR__.'/buyer.php');
