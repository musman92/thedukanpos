<?php

use App\Http\Controllers\Admin\ShiftController;
use Illuminate\Support\Facades\Route;

Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
