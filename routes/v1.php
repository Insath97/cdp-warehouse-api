<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\BranchController;
use App\Http\Controllers\V1\CountryController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\DesignationController;
use App\Http\Controllers\V1\DistrictController;
use App\Http\Controllers\V1\GroupController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\ProvinceController;
use App\Http\Controllers\V1\RoleController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\ItemTypeController;
use App\Http\Controllers\V1\ItemVarietyController;
use App\Http\Controllers\V1\BankController;
use App\Http\Controllers\V1\VehicleController;
use App\Http\Controllers\V1\VehicleLogController;
use App\Http\Controllers\V1\SupplierController;
use App\Http\Controllers\V1\WarehouseController;
use App\Http\Controllers\V1\StockInController;
use App\Http\Controllers\V1\ReceiptController;
use App\Http\Controllers\V1\StockBagController;
use App\Http\Controllers\V1\QualityInspectionController;
use App\Http\Controllers\V1\ActivityLogController;
use App\Http\Controllers\V1\BuyerController;
use App\Http\Controllers\V1\InvoiceController;
use App\Http\Controllers\V1\StockDispatchController;
use App\Http\Controllers\V1\BarcodeTokenController;
use App\Http\Controllers\V1\DatabaseController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\ImportController;
use App\Http\Controllers\V1\SmsController;
use App\Http\Controllers\V1\SettingController;
use App\Http\Controllers\V1\ReportController;
use Illuminate\Support\Facades\Route;

/* public routes */

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
});

/* protected routes */
Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::get('permissions/list', [PermissionController::class, 'getPermissionList']);
    Route::apiResource('permissions', PermissionController::class);

    Route::get('roles/list/', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);

    Route::prefix('users')->group(function () {
        Route::get('list', [UserController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [UserController::class, 'toggleStatus']);
    });
    Route::apiResource('users', UserController::class);

    // Countries
    Route::prefix('countries')->group(function () {
        Route::patch('{id}/toggle-status', [CountryController::class, 'toggleStatus']);
        Route::get('list', [CountryController::class, 'getActiveList']);
    });
    Route::apiResource('countries', CountryController::class);

    // Provinces
    Route::prefix('provinces')->group(function () {
        Route::patch('{id}/toggle-status', [ProvinceController::class, 'toggleStatus']);
        Route::get('list', [ProvinceController::class, 'getProvinceList']);
    });
    Route::apiResource('provinces', ProvinceController::class);

    // Districts
    Route::prefix('districts')->group(function () {
        Route::patch('{id}/toggle-status', [DistrictController::class, 'toggleStatus']);
        Route::get('list', [DistrictController::class, 'getDistrictList']);
    });
    Route::apiResource('districts', DistrictController::class);

    // Branches
    Route::prefix('branches')->group(function () {
        Route::patch('{id}/toggle-status', [BranchController::class, 'toggleStatus']);
        Route::get('list', [BranchController::class, 'getBranchList']);
    });
    Route::apiResource('branches', BranchController::class);

    // Departments
    Route::prefix('departments')->group(function () {
        Route::get('{id}/designations', [DepartmentController::class, 'getDesignations']);
        Route::patch('{id}/toggle-status', [DepartmentController::class, 'toggleStatus']);
    });
    Route::apiResource('departments', DepartmentController::class);

    // Designations
    Route::prefix('designations')->group(function () {
        Route::get('list', [DesignationController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [DesignationController::class, 'toggleStatus']);
    });
    Route::apiResource('designations', DesignationController::class);

    // Groups
    Route::prefix('groups')->group(function () {
        Route::get('list', [GroupController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [GroupController::class, 'toggleStatus']);
    });
    Route::apiResource('groups', GroupController::class);

    // Item Types
    Route::prefix('item-types')->group(function () {
        Route::get('list', [ItemTypeController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [ItemTypeController::class, 'toggleStatus']);
    });
    Route::apiResource('item-types', ItemTypeController::class);

    // Item Varieties
    Route::prefix('item-varieties')->group(function () {
        Route::get('list', [ItemVarietyController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [ItemVarietyController::class, 'toggleStatus']);
    });
    Route::apiResource('item-varieties', ItemVarietyController::class);

    // Banks
    Route::prefix('banks')->group(function () {
        Route::get('list', [BankController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [BankController::class, 'toggleStatus']);
    });
    Route::apiResource('banks', BankController::class);

    // Vehicles
    Route::prefix('vehicles')->group(function () {
        Route::get('list', [VehicleController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [VehicleController::class, 'toggleStatus']);
        Route::patch('{id}/availability', [VehicleController::class, 'updateAvailabilityStatus']);
        Route::get('{id}/logs', [VehicleLogController::class, 'getByVehicle']);
    });
    Route::apiResource('vehicles', VehicleController::class);

    // Vehicle Logs
    Route::prefix('vehicle-logs')->group(function () {
        Route::post('{id}/exit', [VehicleLogController::class, 'exitLog']);
    });
    Route::apiResource('vehicle-logs', VehicleLogController::class);

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('list', [SupplierController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [SupplierController::class, 'toggleStatus']);
    });
    Route::apiResource('suppliers', SupplierController::class);

    // Warehouses
    Route::prefix('warehouses')->group(function () {
        Route::get('accessible', [WarehouseController::class, 'getAccessible']);
        Route::get('list', [WarehouseController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [WarehouseController::class, 'toggleStatus']);
    });
    Route::apiResource('warehouses', WarehouseController::class);

    // Stock In Batches consolidated
    Route::prefix('stock-ins')->group(function () {
        Route::get('list', [StockInController::class, 'getActiveList']);
        Route::patch('{id}/status', [StockInController::class, 'updateStatus']);
    });
    Route::apiResource('stock-ins', StockInController::class);

    // Receipts
    Route::prefix('receipts')->group(function () {
        Route::patch('{id}/status', [ReceiptController::class, 'updateStatus']);
    });
    Route::apiResource('receipts', ReceiptController::class)->only(['index', 'show']);

    // Stock Bags
    Route::prefix('stock-bags')->group(function () {
        Route::get('batch/{batchId}/details', [StockBagController::class, 'getBatchDetails']);
        Route::get('list', [StockBagController::class, 'getActiveList']);
        Route::patch('{id}/status', [StockBagController::class, 'updateStatus']);
    });
    Route::apiResource('stock-bags', StockBagController::class);

    // Quality Inspections
    Route::apiResource('quality-inspections', QualityInspectionController::class);

    // Buyers
    Route::prefix('buyers')->group(function () {
        Route::get('list', [BuyerController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [BuyerController::class, 'toggleStatus']);
    });
    Route::apiResource('buyers', BuyerController::class);

    // Invoices
    Route::prefix('invoices')->group(function () {
        Route::patch('{id}/payment-status', [InvoiceController::class, 'updatePaymentStatus']);
    });
    Route::apiResource('invoices', InvoiceController::class);

    // Stock Dispatches
    Route::prefix('stock-dispatches')->group(function () {
        Route::patch('{id}/confirm', [StockDispatchController::class, 'confirmGatePass']);
        Route::patch('{id}/gate-exit', [StockDispatchController::class, 'recordGateExit']);
    });
    Route::apiResource('stock-dispatches', StockDispatchController::class);

    // Barcode / QR Tokens
    Route::prefix('barcode-tokens')->group(function () {
        Route::post('verify', [BarcodeTokenController::class, 'verifyAndUse']);
        Route::get('{code}/verify', [BarcodeTokenController::class, 'verifyStatus']);
    });
    Route::apiResource('barcode-tokens', BarcodeTokenController::class)->except(['update', 'destroy']);

    // Inventory Reports
    Route::prefix('inventory-reports')->group(function () {
        Route::get('balance', [ReportController::class, 'balance']);
        Route::get('valuation', [ReportController::class, 'valuation']);
        Route::get('aging', [ReportController::class, 'aging']);
        Route::get('alerts', [ReportController::class, 'alerts']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('batch-wise', [ReportController::class, 'batchWise']);
    });

    // Activity Logs (Read-only: Get All and Get By ID)
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('{id}', [ActivityLogController::class, 'show']);
    });

    // Executive Dashboard & Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('summary', [DashboardController::class, 'summary']);
        Route::get('analytics', [DashboardController::class, 'analytics']);
        Route::get('operational', [DashboardController::class, 'operational']);
    });

    // Bulk Import Engine & Template Generator
    Route::prefix('import')->middleware('throttle:uploads')->group(function () {
        Route::get('tables', [ImportController::class, 'listTables']);
        Route::get('catalog', [ImportController::class, 'index']);
        Route::get('{table}/template', [ImportController::class, 'downloadTemplate']);
        Route::post('{table}', [ImportController::class, 'import']);
    });

    // Dialog SMS Gateway
    Route::prefix('sms')->group(function () {
        Route::get('logs', [SmsController::class, 'index']);
        Route::get('logs/{id}', [SmsController::class, 'show']);
        Route::post('send', [SmsController::class, 'send']);
        Route::get('balance', [SmsController::class, 'balance']);
    });

    // System Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::post('/', [SettingController::class, 'update']);
    });

    // Database Export
    Route::get('database/export', [DatabaseController::class, 'export']);

    // Data Export (Real data as CSV/Excel)
    Route::prefix('export')->group(function () {
        Route::get('tables', [DatabaseController::class, 'exportData']);
        Route::get('{table}', [DatabaseController::class, 'exportTable']);
        Route::get('all/excel', [DatabaseController::class, 'exportAllTables']);
    });
});
