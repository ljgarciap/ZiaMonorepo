<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Admin & SuperAdmin Routes
    Route::middleware(['role:superadmin,admin'])->prefix('admin')->group(function () {
        // ... (Admin routes remain same) ...
        // Audit Logs (SuperAdmin only - controller checks logic)
        Route::get('/audit-logs', [\App\Http\Controllers\Api\Admin\AdminAuditController::class, 'index']);

        // Companies & Periods Management
        Route::get('/companies', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'index']);
        Route::post('/companies', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'store']);
        Route::put('/companies/{company}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'update']);
        Route::delete('/companies/{company}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'destroy']);
        Route::post('/companies/{company}/periods', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'addPeriod']);
        Route::put('/periods/{period}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'updatePeriod']);
        Route::delete('/periods/{period}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'deletePeriod']);

        // Company Specific Factors
        Route::get('/companies/{company}/factors', [\App\Http\Controllers\Api\Admin\AdminCompanyFactorController::class, 'index']);
        Route::put('/companies/{company}/factors', [\App\Http\Controllers\Api\Admin\AdminCompanyFactorController::class, 'update']);

        // Users Management
        Route::get('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'store']);
        Route::put('/users/{user}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'destroy']);
        Route::post('/users/{id}/restore', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'restore']);

        // Superadmin-only Master Data management
        Route::middleware(['role:superadmin'])->group(function () {
            // Sectores Management
            Route::apiResource('/sectors', \App\Http\Controllers\Api\Admin\CompanySectorController::class);

            // Master Data (Categories & Factors)
            Route::get('/categories', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'indexCategories']);
            Route::post('/categories', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'storeCategory']);
            Route::delete('/categories/{category}', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'deleteCategory']);
            Route::post('/factors', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'storeFactor']);
            Route::put('/factors/{factor}', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'updateFactor']);
            Route::delete('/factors/{factor}', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'deleteFactor']);

            // Formulas Management
            Route::apiResource('/formulas', \App\Http\Controllers\Api\Admin\CalculationFormulaController::class);

            // Units Management
            Route::apiResource('/units', \App\Http\Controllers\Api\Admin\AdminUnitController::class);

            // Scopes Management
            Route::apiResource('/scopes', \App\Http\Controllers\Api\Admin\AdminScopeController::class);
        });
    });

    // Context-Aware Routes (Routes that depend on selected company context)
    Route::middleware(['context.aware'])->group(function () {
        // Public Dictionaries (Master Data for usage in forms/dashboards)
        Route::get('/companies', [App\Http\Controllers\Api\CompanyController::class, 'index']);
        Route::get('/companies/{id}/periods', [App\Http\Controllers\Api\CompanyController::class, 'periods']);
        
        // Carbon Emissions Management
        Route::post('/periods/{period}/emissions', [App\Http\Controllers\Api\CarbonEmissionController::class, 'store']);
        Route::get('/periods/{period}/emissions', [App\Http\Controllers\Api\CarbonEmissionController::class, 'index']);
        Route::get('/companies/{company}/emissions/history', [App\Http\Controllers\Api\CarbonEmissionController::class, 'history']);
        Route::delete('/emissions/{emission}', [App\Http\Controllers\Api\CarbonEmissionController::class, 'destroy']);
        
        Route::get('/dictionaries/factors', [App\Http\Controllers\Api\MasterDataController::class, 'emissionFactors']);

        // Dashboard Routes
        Route::get('/dashboard/summary', [App\Http\Controllers\Api\DashboardController::class, 'summary']);
        Route::get('/dashboard/trends', [App\Http\Controllers\Api\DashboardController::class, 'trends']);

        // Reporting Routes
        Route::get('/reports/periods/{period}/pdf', [App\Http\Controllers\Api\ReportController::class, 'pdfSummary']);
        Route::get('/reports/periods/{period}/excel', [App\Http\Controllers\Api\ReportController::class, 'excelExport']);

        // AI Sidecar Route
        Route::get('/ai/recommendations', [\App\Http\Controllers\Api\AISidecarController::class, 'getRecommendations']);
    });
});

