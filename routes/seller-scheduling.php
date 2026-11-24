<?php

use App\Http\Controllers\Seller\ShowingController;
use App\Http\Controllers\Seller\WindowController;
use Illuminate\Support\Facades\Route;

Route::post('/listings/{listing}/windows', [WindowController::class, 'store'])->whereNumber('listing')->name('windows.store');
Route::post('/windows/{window}/close', [WindowController::class, 'close'])->whereNumber('window')->name('windows.close');
Route::post('/showings/{showing}/cancel', [ShowingController::class, 'cancel'])->whereNumber('showing')->name('showings.cancel');
