<?php

use Addons\Bookings\Http\Controllers\Admin\BookingController;
use Addons\Bookings\Http\Controllers\Admin\BookingSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
Route::post('/bookings/{booking}/convert', [BookingController::class, 'convert'])->name('bookings.convert');
Route::get('/bookings/product-settings/{product}', [BookingController::class, 'productSettings'])
    ->name('bookings.product-settings.show');

Route::get('/booking-settings', [BookingSettingsController::class, 'edit'])->name('bookings.settings.edit');
Route::put('/booking-settings', [BookingSettingsController::class, 'update'])->name('bookings.settings.update');
Route::get('/booking-settings/google/connect', [BookingSettingsController::class, 'connect'])
    ->name('bookings.google.connect');
Route::get('/booking-settings/google/callback', [BookingSettingsController::class, 'callback'])
    ->name('bookings.google.callback');
Route::delete('/booking-settings/google', [BookingSettingsController::class, 'disconnect'])
    ->name('bookings.google.disconnect');
