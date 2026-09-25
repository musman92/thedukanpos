<?php

use Addons\Bookings\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::get('/book/{tenant_code}', [PublicBookingController::class, 'show'])
    ->name('bookings.public.show');
Route::post('/book/{tenant_code}', [PublicBookingController::class, 'store'])
    ->name('bookings.public.store');
