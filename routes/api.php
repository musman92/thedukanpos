<?php

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ActivityLogController as ApiActivityLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController as ApiBranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\OfficeController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController as ApiProfileController;
use App\Http\Controllers\Api\V1\ReportController as ApiReportController;
use App\Http\Controllers\Api\V1\RoleController as ApiRoleController;
use App\Http\Controllers\Api\V1\SupplierController as ApiSupplierController;
use App\Http\Controllers\Api\V1\RackController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SettingController as ApiSettingController;
use App\Http\Controllers\Api\V1\SubscriptionController as ApiSubscriptionController;
use App\Http\Controllers\Api\V1\TaxController as ApiTaxController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController as ApiUserController;
use App\Http\Controllers\Api\V1\VariationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::get('/register/shop-code', [AuthController::class, 'shopCode'])->middleware('throttle:60,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    Route::middleware(['tenancy.header', 'auth:sanctum', 'api.permission'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ApiProfileController::class, 'show']);
        Route::put('/profile', [ApiProfileController::class, 'update']);
        Route::put('/profile/password', [ApiProfileController::class, 'updatePassword']);

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

        Route::get('/users/options', [ApiUserController::class, 'options'])->name('api.v1.users.options');
        Route::get('/users', [ApiUserController::class, 'index'])->name('api.v1.users.index');
        Route::post('/users', [ApiUserController::class, 'store'])->name('api.v1.users.store');
        Route::get('/users/{user}', [ApiUserController::class, 'show'])->name('api.v1.users.show');
        Route::match(['put', 'patch'], '/users/{user}', [ApiUserController::class, 'update'])
            ->name('api.v1.users.update');
        Route::delete('/users/{user}', [ApiUserController::class, 'destroy'])->name('api.v1.users.destroy');

        Route::get('/branches', [ApiBranchController::class, 'index'])->name('api.v1.branches.index');
        Route::post('/branches', [ApiBranchController::class, 'store'])->name('api.v1.branches.store');
        Route::get('/branches/{branch}', [ApiBranchController::class, 'show'])->name('api.v1.branches.show');
        Route::match(['put', 'patch'], '/branches/{branch}', [ApiBranchController::class, 'update'])
            ->name('api.v1.branches.update');
        Route::delete('/branches/{branch}', [ApiBranchController::class, 'destroy'])->name('api.v1.branches.destroy');

        Route::get('/roles', [ApiRoleController::class, 'index'])->name('api.v1.roles.index');
        Route::post('/roles', [ApiRoleController::class, 'store'])->name('api.v1.roles.store');
        Route::get('/roles/{role}', [ApiRoleController::class, 'show'])->name('api.v1.roles.show');
        Route::match(['put', 'patch'], '/roles/{role}', [ApiRoleController::class, 'update'])
            ->name('api.v1.roles.update');
        Route::delete('/roles/{role}', [ApiRoleController::class, 'destroy'])->name('api.v1.roles.destroy');

        Route::get('/activity', [ApiActivityLogController::class, 'index'])->name('api.v1.activity.index');
        Route::post('/activity/toggle', [ApiActivityLogController::class, 'toggle'])->name('api.v1.activity.toggle');

        Route::get('/subscription', [ApiSubscriptionController::class, 'index'])->name('api.v1.subscription.index');

        Route::get('/settings', [ApiSettingController::class, 'show'])->name('api.v1.settings.show');
        // POST is allowed so the phone can upload a logo (PHP does not read files on PUT).
        Route::match(['put', 'patch', 'post'], '/settings', [ApiSettingController::class, 'update'])
            ->name('api.v1.settings.update');

        Route::get('/office/{resource}/options', [ModuleController::class, 'options'])
            ->name('api.v1.office.options');
        Route::post('/office/{resource}/{id}/action', [ModuleController::class, 'action'])
            ->whereNumber('id')
            ->name('api.v1.office.action');
        Route::get('/office/{resource}/{id}', [ModuleController::class, 'show'])
            ->whereNumber('id')
            ->name('api.v1.office.show');
        Route::post('/office/{resource}', [ModuleController::class, 'store'])
            ->name('api.v1.office.store');
        Route::match(['put', 'patch'], '/office/{resource}/{id}', [ModuleController::class, 'update'])
            ->whereNumber('id')
            ->name('api.v1.office.update');
        Route::delete('/office/{resource}/{id}', [ModuleController::class, 'destroy'])
            ->whereNumber('id')
            ->name('api.v1.office.destroy');

        Route::get('/office/{resource}', [OfficeController::class, 'index'])
            ->where('resource', '[a-z0-9\-]+')
            ->name('api.v1.office.index');
        Route::get('/reports', [ApiReportController::class, 'index'])->name('api.v1.reports.index');
        Route::get('/reports/{report}', [ApiReportController::class, 'show'])
            ->where('report', '[a-z0-9\-]+')
            ->name('api.v1.reports.show');

        Route::get('/taxes', [ApiTaxController::class, 'index'])->name('api.v1.taxes.index');
        Route::post('/taxes', [ApiTaxController::class, 'store'])->name('api.v1.taxes.store');
        Route::get('/taxes/{tax}', [ApiTaxController::class, 'show'])->name('api.v1.taxes.show');
        Route::match(['put', 'patch'], '/taxes/{tax}', [ApiTaxController::class, 'update'])
            ->name('api.v1.taxes.update');
        Route::delete('/taxes/{tax}', [ApiTaxController::class, 'destroy'])->name('api.v1.taxes.destroy');
    });
});
