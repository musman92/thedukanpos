<?php

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SupplierController as ApiSupplierController;
use App\Http\Controllers\Api\V1\RackController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\TaxController as ApiTaxController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\VariationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['tenancy.header', 'auth:sanctum'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/media/{path}', [MediaController::class, 'show'])
            ->where('path', '.*')
            ->name('api.v1.media.show');

        Route::get('/brands', [BrandController::class, 'index'])->name('api.v1.brands.index');
        Route::post('/brands', [BrandController::class, 'store'])->name('api.v1.brands.store');
        Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('api.v1.brands.show');
        // POST is allowed so mobile clients can upload images via multipart (PHP does not populate files on PUT).
        Route::match(['put', 'patch', 'post'], '/brands/{brand}', [BrandController::class, 'update'])
            ->name('api.v1.brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('api.v1.brands.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])->name('api.v1.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('api.v1.categories.store');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.v1.categories.show');
        Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])
            ->name('api.v1.categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.v1.categories.destroy');

        Route::get('/units', [UnitController::class, 'index'])->name('api.v1.units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('api.v1.units.store');
        Route::get('/units/{unit}', [UnitController::class, 'show'])->name('api.v1.units.show');
        Route::match(['put', 'patch'], '/units/{unit}', [UnitController::class, 'update'])
            ->name('api.v1.units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('api.v1.units.destroy');

        Route::get('/variations', [VariationController::class, 'index'])->name('api.v1.variations.index');
        Route::post('/variations', [VariationController::class, 'store'])->name('api.v1.variations.store');
        Route::get('/variations/{variation}', [VariationController::class, 'show'])->name('api.v1.variations.show');
        Route::match(['put', 'patch'], '/variations/{variation}', [VariationController::class, 'update'])
            ->name('api.v1.variations.update');
        Route::delete('/variations/{variation}', [VariationController::class, 'destroy'])->name('api.v1.variations.destroy');

        Route::get('/sections', [SectionController::class, 'index'])->name('api.v1.sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('api.v1.sections.store');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('api.v1.sections.show');
        Route::match(['put', 'patch'], '/sections/{section}', [SectionController::class, 'update'])
            ->name('api.v1.sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('api.v1.sections.destroy');

        Route::get('/racks', [RackController::class, 'index'])->name('api.v1.racks.index');
        Route::post('/racks', [RackController::class, 'store'])->name('api.v1.racks.store');
        Route::get('/racks/{rack}', [RackController::class, 'show'])->name('api.v1.racks.show');
        Route::match(['put', 'patch'], '/racks/{rack}', [RackController::class, 'update'])
            ->name('api.v1.racks.update');
        Route::delete('/racks/{rack}', [RackController::class, 'destroy'])->name('api.v1.racks.destroy');

        Route::get('/products', [ProductController::class, 'index'])->name('api.v1.products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('api.v1.products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('api.v1.products.show');
        Route::match(['put', 'patch', 'post'], '/products/{product}', [ProductController::class, 'update'])
            ->name('api.v1.products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('api.v1.products.destroy');

        Route::get('/customers', [CustomerController::class, 'index'])->name('api.v1.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('api.v1.customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('api.v1.customers.show');
        Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])
            ->name('api.v1.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('api.v1.customers.destroy');

        Route::get('/suppliers', [ApiSupplierController::class, 'index'])->name('api.v1.suppliers.index');
        Route::post('/suppliers', [ApiSupplierController::class, 'store'])->name('api.v1.suppliers.store');
        Route::get('/suppliers/{supplier}', [ApiSupplierController::class, 'show'])->name('api.v1.suppliers.show');
        Route::match(['put', 'patch'], '/suppliers/{supplier}', [ApiSupplierController::class, 'update'])
            ->name('api.v1.suppliers.update');
        Route::delete('/suppliers/{supplier}', [ApiSupplierController::class, 'destroy'])->name('api.v1.suppliers.destroy');

        Route::get('/accounts', [AccountController::class, 'index'])->name('api.v1.accounts.index');
        Route::post('/accounts', [AccountController::class, 'store'])->name('api.v1.accounts.store');
        Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('api.v1.accounts.show');
        Route::match(['put', 'patch'], '/accounts/{account}', [AccountController::class, 'update'])
            ->name('api.v1.accounts.update');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('api.v1.accounts.destroy');

        Route::get('/taxes', [ApiTaxController::class, 'index'])->name('api.v1.taxes.index');
        Route::post('/taxes', [ApiTaxController::class, 'store'])->name('api.v1.taxes.store');
        Route::get('/taxes/{tax}', [ApiTaxController::class, 'show'])->name('api.v1.taxes.show');
        Route::match(['put', 'patch'], '/taxes/{tax}', [ApiTaxController::class, 'update'])
            ->name('api.v1.taxes.update');
        Route::delete('/taxes/{tax}', [ApiTaxController::class, 'destroy'])->name('api.v1.taxes.destroy');
    });
});
