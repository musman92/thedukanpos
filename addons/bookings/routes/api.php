<?php

use Addons\Bookings\Http\Controllers\Api\V1\BookingController;
use Illuminate\Support\Facades\Route;

// Same names and parameters as Route::apiResource('bookings'), split so each
// verb carries the permission it needs.
Route::middleware('can:bookings.view')->group(function () {
    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
});

Route::middleware('can:bookings.manage')->group(function () {
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::match(['put', 'patch'], 'bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
});
