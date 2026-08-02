<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CatalogMasterController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Finance\AccountController;
use App\Http\Controllers\Admin\Finance\CustomerPaymentController;
use App\Http\Controllers\Admin\Finance\EmployeePaymentController;
use App\Http\Controllers\Admin\Finance\ExpenseController;
use App\Http\Controllers\Admin\Finance\MoneySourceController;
use App\Http\Controllers\Admin\Finance\SupplierPaymentController;
use App\Http\Controllers\Admin\Finance\TaxController;
use App\Http\Controllers\Admin\Finance\TransactionController;
use App\Http\Controllers\Admin\Hr\AdjustmentController;
use App\Http\Controllers\Admin\Hr\AttendanceController;
use App\Http\Controllers\Admin\Hr\LeaveController;
use App\Http\Controllers\Admin\Hr\PayrollController;
use App\Http\Controllers\Admin\ImportExportController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventoryStockController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\QuotationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportHubController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\RackController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SerialController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VariationController;
use App\Http\Controllers\Platform\AuthController as PlatformAuthController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\InvoiceController as PlatformInvoiceController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SupportLoginController;
use App\Http\Middleware\EnsurePlatformAuthenticated;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenancy.session', 'auth'])->group(function () {
    Route::redirect('/', '/pos');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::get('/pos/catalog', [PosController::class, 'catalog'])->name('pos.catalog');
    Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/parked', [PosController::class, 'parked'])->name('pos.parked');
    Route::get('/pos/today', [PosController::class, 'today'])->name('pos.today');
    Route::get('/pos/today/{sale}', [PosController::class, 'todayShow'])->name('pos.today.show');
    Route::post('/pos/today/{sale}/void', [PosController::class, 'todayVoid'])->name('pos.today.void');
    Route::get('/pos/deliveries', [PosController::class, 'deliveries'])->name('pos.deliveries');
    Route::patch('/pos/deliveries/{sale}', [PosController::class, 'updateDelivery'])->name('pos.deliveries.update');
    Route::post('/pos/parked', [PosController::class, 'park'])->name('pos.park');
    Route::put('/pos/parked/{sale}', [PosController::class, 'updateParked'])->name('pos.parked.update');
    Route::delete('/pos/parked/{sale}', [PosController::class, 'discardParked'])->name('pos.parked.discard');
    Route::get('/pos/receipts/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::post('/branches/switch', [BranchController::class, 'switch'])->name('branches.switch');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

        Route::get('/catalog', [CatalogMasterController::class, 'index'])->name('catalog.index');

        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
        Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        Route::get('/variations', [VariationController::class, 'index'])->name('variations.index');
        Route::post('/variations', [VariationController::class, 'store'])->name('variations.store');
        Route::put('/variations/{variation}', [VariationController::class, 'update'])->name('variations.update');
        Route::delete('/variations/{variation}', [VariationController::class, 'destroy'])->name('variations.destroy');

        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

        Route::get('/racks', [RackController::class, 'index'])->name('racks.index');
        Route::post('/racks', [RackController::class, 'store'])->name('racks.store');
        Route::put('/racks/{rack}', [RackController::class, 'update'])->name('racks.update');
        Route::delete('/racks/{rack}', [RackController::class, 'destroy'])->name('racks.destroy');
        Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media.show');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('/customers/payments', [CustomerController::class, 'receivePayment'])->name('customers.payments.store');

        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
        Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');

        Route::get('/inventory/stock', [InventoryStockController::class, 'index'])->name('inventory.stock');
        Route::get('/inventory/low-stock', [InventoryStockController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('/inventory/product-ledger', [InventoryStockController::class, 'productLedger'])->name('inventory.product-ledger');
        Route::get('/inventory/adjustments', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
        Route::get('/inventory/adjustments/stock', [InventoryController::class, 'adjustmentStock'])->name('inventory.adjustments.stock');
        Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');
        Route::put('/inventory/adjustments/{stockAdjustment}', [InventoryController::class, 'updateAdjustment'])->name('inventory.adjustments.update');
        Route::delete('/inventory/adjustments/{stockAdjustment}', [InventoryController::class, 'destroyAdjustment'])->name('inventory.adjustments.destroy');
        Route::get('/inventory/transfers', [InventoryController::class, 'transfers'])->name('inventory.transfers');
        Route::post('/inventory/transfers', [InventoryController::class, 'storeTransfer'])->name('inventory.transfers.store');
        Route::delete('/inventory/transfers/{stockTransfer}', [InventoryController::class, 'destroyTransfer'])->name('inventory.transfers.destroy');
        Route::get('/inventory/damages', [InventoryController::class, 'damages'])->name('inventory.damages');
        Route::post('/inventory/damages', [InventoryController::class, 'storeDamage'])->name('inventory.damages.store');
        Route::put('/inventory/damages/{stockDamage}', [InventoryController::class, 'updateDamage'])->name('inventory.damages.update');
        Route::delete('/inventory/damages/{stockDamage}', [InventoryController::class, 'destroyDamage'])->name('inventory.damages.destroy');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{sale}', [OrderController::class, 'show'])->name('orders.show');

        Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
        Route::get('/quotations/{quotation}/download', [QuotationController::class, 'download'])->name('quotations.download');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');

        Route::get('/returns/purchases', [ReturnController::class, 'purchaseIndex'])->name('returns.purchases.index');
        Route::get('/returns/purchases/create', [ReturnController::class, 'purchaseCreate'])->name('returns.purchases.create');
        Route::post('/returns/purchases', [ReturnController::class, 'purchaseStore'])->name('returns.purchases.store');
        Route::get('/returns/purchases/{purchaseReturn}', [ReturnController::class, 'purchaseShow'])->name('returns.purchases.show');
        Route::put('/returns/purchases/{purchaseReturn}', [ReturnController::class, 'purchaseUpdate'])->name('returns.purchases.update');
        Route::delete('/returns/purchases/{purchaseReturn}', [ReturnController::class, 'purchaseDestroy'])->name('returns.purchases.destroy');
        Route::get('/returns/sales', [ReturnController::class, 'saleIndex'])->name('returns.sales.index');
        Route::get('/returns/sales/create', [ReturnController::class, 'saleCreate'])->name('returns.sales.create');
        Route::post('/returns/sales', [ReturnController::class, 'saleStore'])->name('returns.sales.store');
        Route::get('/returns/sales/{saleReturn}', [ReturnController::class, 'saleShow'])->name('returns.sales.show');

        Route::get('/reports', [ReportHubController::class, 'index'])->name('reports.hub');
        Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/sales/export', [ReportController::class, 'salesExport'])->name('reports.sales.export');
        Route::get('/reports/products', [ReportController::class, 'productSales'])->name('reports.products');
        Route::get('/reports/top-items', [ReportHubController::class, 'topItems'])->name('reports.top-items');
        Route::get('/reports/daily-sales', [ReportHubController::class, 'dailySales'])->name('reports.daily-sales');
        Route::get('/reports/sales-by-category', [ReportHubController::class, 'salesByCategory'])->name('reports.sales-by-category');
        Route::get('/reports/payment-methods', [ReportHubController::class, 'paymentMethods'])->name('reports.payment-methods');
        Route::get('/reports/money-source-txns', [ReportHubController::class, 'moneySourceTxns'])->name('reports.money-source-txns');
        Route::get('/reports/foc', [ReportHubController::class, 'foc'])->name('reports.foc');
        Route::get('/reports/order-history', [ReportHubController::class, 'orderHistory'])->name('reports.order-history');
        Route::get('/reports/discounts', [ReportHubController::class, 'discounts'])->name('reports.discounts');
        Route::get('/reports/tax-summary', [ReportHubController::class, 'taxSummary'])->name('reports.tax-summary');
        Route::get('/reports/stock-on-hand', fn () => redirect()->route('admin.inventory.stock'))->name('reports.stock-on-hand');
        Route::get('/reports/receivables', [ReportHubController::class, 'receivables'])->name('reports.receivables');
        Route::get('/reports/payables', [ReportHubController::class, 'payables'])->name('reports.payables');
        Route::get('/reports/customer-credits', [ReportHubController::class, 'customerCredits'])->name('reports.customer-credits');
        Route::get('/reports/supplier-payments', [ReportHubController::class, 'supplierPayments'])->name('reports.supplier-payments');
        Route::get('/reports/purchases', [ReportHubController::class, 'purchases'])->name('reports.purchases');
        Route::get('/reports/expenses', [ReportHubController::class, 'expenses'])->name('reports.expenses');
        Route::get('/reports/account-statement', [ReportHubController::class, 'accountStatement'])->name('reports.account-statement');
        Route::get('/reports/weekly-closing', [ReportHubController::class, 'weeklyClosing'])->name('reports.weekly-closing');
        Route::get('/reports/monthly-closing', [ReportHubController::class, 'monthlyClosing'])->name('reports.monthly-closing');
        Route::get('/reports/gross-margin', [ReportHubController::class, 'grossMargin'])->name('reports.gross-margin');
        Route::get('/reports/profit-loss', [ReportHubController::class, 'profitLoss'])->name('reports.profit-loss');
        Route::get('/reports/shifts-z', [ReportHubController::class, 'shiftsZ'])->name('reports.shifts-z');

        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/serials', [SerialController::class, 'index'])->name('serials.index');
        Route::post('/serials', [SerialController::class, 'store'])->name('serials.store');

        Route::get('/import-export', [ImportExportController::class, 'index'])->name('import-export.index');
        Route::get('/import-export/products/export', [ImportExportController::class, 'exportProducts'])->name('import-export.products.export');
        Route::get('/import-export/products/sample', [ImportExportController::class, 'sampleProducts'])->name('import-export.products.sample');
        Route::post('/import-export/products/import', [ImportExportController::class, 'importProducts'])->name('import-export.products.import');
        Route::get('/import-export/customers/export', [ImportExportController::class, 'exportCustomers'])->name('import-export.customers.export');
        Route::get('/import-export/customers/sample', [ImportExportController::class, 'sampleCustomers'])->name('import-export.customers.sample');
        Route::post('/import-export/customers/import', [ImportExportController::class, 'importCustomers'])->name('import-export.customers.import');
        Route::get('/import-export/brands/export', [ImportExportController::class, 'exportBrands'])->name('import-export.brands.export');
        Route::get('/import-export/brands/sample', [ImportExportController::class, 'sampleBrands'])->name('import-export.brands.sample');
        Route::post('/import-export/brands/import', [ImportExportController::class, 'importBrands'])->name('import-export.brands.import');
        Route::get('/import-export/categories/export', [ImportExportController::class, 'exportCategories'])->name('import-export.categories.export');
        Route::get('/import-export/categories/sample', [ImportExportController::class, 'sampleCategories'])->name('import-export.categories.sample');
        Route::post('/import-export/categories/import', [ImportExportController::class, 'importCategories'])->name('import-export.categories.import');
        Route::get('/import-export/units/export', [ImportExportController::class, 'exportUnits'])->name('import-export.units.export');
        Route::get('/import-export/units/sample', [ImportExportController::class, 'sampleUnits'])->name('import-export.units.sample');
        Route::post('/import-export/units/import', [ImportExportController::class, 'importUnits'])->name('import-export.units.import');
        Route::get('/import-export/variations/export', [ImportExportController::class, 'exportVariations'])->name('import-export.variations.export');
        Route::get('/import-export/variations/sample', [ImportExportController::class, 'sampleVariations'])->name('import-export.variations.sample');
        Route::post('/import-export/variations/import', [ImportExportController::class, 'importVariations'])->name('import-export.variations.import');
        Route::get('/import-export/sections/export', [ImportExportController::class, 'exportSections'])->name('import-export.sections.export');
        Route::get('/import-export/sections/sample', [ImportExportController::class, 'sampleSections'])->name('import-export.sections.sample');
        Route::post('/import-export/sections/import', [ImportExportController::class, 'importSections'])->name('import-export.sections.import');
        Route::get('/import-export/racks/export', [ImportExportController::class, 'exportRacks'])->name('import-export.racks.export');
        Route::get('/import-export/racks/sample', [ImportExportController::class, 'sampleRacks'])->name('import-export.racks.sample');
        Route::post('/import-export/racks/import', [ImportExportController::class, 'importRacks'])->name('import-export.racks.import');

        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::post('/activity/toggle', [ActivityLogController::class, 'toggle'])->name('activity.toggle');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::redirect('/staff', '/admin/users');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
            Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

            Route::get('/taxes', [TaxController::class, 'index'])->name('taxes.index');
            Route::post('/taxes', [TaxController::class, 'store'])->name('taxes.store');
            Route::put('/taxes/{tax}', [TaxController::class, 'update'])->name('taxes.update');
            Route::delete('/taxes/{tax}', [TaxController::class, 'destroy'])->name('taxes.destroy');

            Route::get('/money-sources', [MoneySourceController::class, 'index'])->name('money-sources.index');
            Route::post('/money-sources', [MoneySourceController::class, 'store'])->name('money-sources.store');
            Route::get('/money-sources/transfer', [MoneySourceController::class, 'transferForm'])->name('money-sources.transfer.create');
            Route::post('/money-sources/transfer', [MoneySourceController::class, 'transfer'])->name('money-sources.transfer');
            Route::get('/money-sources/owner-withdrawal', [MoneySourceController::class, 'ownerWithdrawalForm'])->name('money-sources.owner-withdrawal.create');
            Route::post('/money-sources/owner-withdrawal', [MoneySourceController::class, 'ownerWithdrawal'])->name('money-sources.owner-withdrawal');
            Route::get('/money-sources/reports', [MoneySourceController::class, 'reports'])->name('money-sources.reports');
            Route::put('/money-sources/{moneySource}', [MoneySourceController::class, 'update'])->name('money-sources.update');
            Route::delete('/money-sources/{moneySource}', [MoneySourceController::class, 'destroy'])->name('money-sources.destroy');

            Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
            Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

            Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
            Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
            Route::put('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'update'])->name('supplier-payments.update');
            Route::delete('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'destroy'])->name('supplier-payments.destroy');

            Route::get('/customer-payments', [CustomerPaymentController::class, 'index'])->name('customer-payments.index');
            Route::post('/customer-payments', [CustomerPaymentController::class, 'store'])->name('customer-payments.store');
            Route::put('/customer-payments/{customerPayment}', [CustomerPaymentController::class, 'update'])->name('customer-payments.update');
            Route::delete('/customer-payments/{customerPayment}', [CustomerPaymentController::class, 'destroy'])->name('customer-payments.destroy');

            Route::get('/employee-payments', [EmployeePaymentController::class, 'index'])->name('employee-payments.index');
            Route::post('/employee-payments', [EmployeePaymentController::class, 'store'])->name('employee-payments.store');
            Route::delete('/employee-payments/{employeePayment}', [EmployeePaymentController::class, 'destroy'])->name('employee-payments.destroy');
        });

        Route::prefix('hr')->name('hr.')->group(function () {
            Route::redirect('/employees', '/admin/users')->name('employees.index');

            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::post('/attendance/action/{user}', [AttendanceController::class, 'action'])->name('attendance.action');
            Route::put('/attendance/{record}', [AttendanceController::class, 'update'])->name('attendance.update');
            Route::delete('/attendance/{record}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

            Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
            Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
            Route::post('/leaves/{leave}/review', [LeaveController::class, 'review'])->name('leaves.review');
            Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy'])->name('leaves.destroy');

            Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
            Route::post('/payroll', [PayrollController::class, 'create'])->name('payroll.store');
            Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
            Route::post('/payroll/{payroll}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');

            Route::get('/adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
            Route::post('/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
            Route::put('/adjustments/{adjustment}', [AdjustmentController::class, 'update'])->name('adjustments.update');
            Route::delete('/adjustments/{adjustment}', [AdjustmentController::class, 'destroy'])->name('adjustments.destroy');
        });
    });
});

Route::get('/support-login/{token}', SupportLoginController::class)->name('support-login');

Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('/login', [PlatformAuthController::class, 'create'])->name('login');
    Route::post('/login', [PlatformAuthController::class, 'store'])->name('login.store');

    Route::middleware(EnsurePlatformAuthenticated::class)->group(function () {
        Route::post('/logout', [PlatformAuthController::class, 'destroy'])->name('logout');
        Route::get('/', PlatformDashboardController::class)->name('dashboard');
        Route::get('/dashboard', PlatformDashboardController::class);
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::put('/tenants/{tenant}/billing', [TenantController::class, 'updateBilling'])->name('tenants.billing');
        Route::post('/tenants/{tenant}/support-login', [TenantController::class, 'createSupportLogin'])->name('tenants.support-login');
        Route::post('/tenants/{tenant}/seed-demo', [TenantController::class, 'seedDemo'])->name('tenants.seed-demo');
        Route::post('/tenants/{tenant}/addons/{addon}/install', [TenantController::class, 'installAddon'])->name('tenants.addons.install');
        Route::delete('/tenants/{tenant}/addons/{addon}', [TenantController::class, 'removeAddon'])->name('tenants.addons.remove');

        Route::get('/invoices', [PlatformInvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices', [PlatformInvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/paid', [PlatformInvoiceController::class, 'markPaid'])->name('invoices.paid');
    });
});

require __DIR__.'/auth.php';
