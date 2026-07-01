<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

// Internal endpoint for zia-agent Python microservice (Docker network only)
Route::middleware(\App\Http\Middleware\InternalOnly::class)
    ->post('/internal/calculate', [App\Http\Controllers\Api\InternalCalculationController::class, 'calculate']);

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
        // Audit Logs — superadmin sees all; admin sees only users of their companies
        Route::get('/audit-logs', [\App\Http\Controllers\Api\Admin\AdminAuditController::class, 'index']);

        // Companies Management — admin: R solo (A02: write es exclusivo del superadmin)
        Route::get('/companies', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'index']);

        // Company Specific Factors
        Route::get('/companies/{company}/factors', [\App\Http\Controllers\Api\Admin\AdminCompanyFactorController::class, 'index']);
        Route::put('/companies/{company}/factors', [\App\Http\Controllers\Api\Admin\AdminCompanyFactorController::class, 'update']);

        // Operational Units — admin writes + user assignment
        Route::post('/companies/{company}/units', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'store']);
        Route::put('/companies/{company}/units/{unit}', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'update']);
        Route::delete('/companies/{company}/units/{unit}', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'destroy']);
        Route::post('/companies/{company}/units/{unit}/assign', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'assignUser']);
        Route::post('/companies/{company}/units/{unit}/unassign', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'unassignUser']);

        // Users Management (admin: CRU only — destroy is superadmin-only, enforced in controller)
        Route::get('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'store']);
        Route::put('/users/{user}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'destroy']);
        Route::post('/users/{id}/restore', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'restore']);

        // SA-17: estadísticas globales de plataforma — solo superadmin
        Route::middleware(['role:superadmin'])->get('/platform-stats', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'platformStats']);

        // Superadmin-only operations
        Route::middleware(['role:superadmin'])->group(function () {
            // Companies write — superadmin only (A02)
            Route::post('/companies', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'store']);
            Route::put('/companies/{company}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'update']);
            Route::delete('/companies/{company}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'destroy']);

            // Period lifecycle (create / update / delete / close) — matrix: admin = R only
            Route::post('/companies/{company}/periods', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'addPeriod']);
            Route::put('/periods/{period}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'updatePeriod']);
            Route::delete('/periods/{period}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'deletePeriod']);
            Route::post('/periods/{period}/close', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'closePeriod']);
            Route::post('/periods/{period}/reopen', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'reopenPeriod']);

            // Company Groups
            Route::prefix('groups')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\CompanyGroupController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\CompanyGroupController::class, 'store']);
                Route::get('/{group}/summary', [App\Http\Controllers\Api\CompanyGroupController::class, 'summary']);
                Route::post('/{group}/companies', [App\Http\Controllers\Api\CompanyGroupController::class, 'addCompany']);
                Route::delete('/{group}/companies', [App\Http\Controllers\Api\CompanyGroupController::class, 'removeCompany']);
                Route::delete('/{group}', [App\Http\Controllers\Api\CompanyGroupController::class, 'destroy']);
            });

            // Master Data (Categories, Factors, Formulas, Units, Scopes, Sectors)
            Route::apiResource('/sectors', \App\Http\Controllers\Api\Admin\CompanySectorController::class);
            Route::get('/categories', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'indexCategories']);
            Route::post('/categories', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'storeCategory']);
            Route::delete('/categories/{category}', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'deleteCategory']);
            Route::post('/factors', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'storeFactor']);
            Route::put('/factors/{factor}', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'updateFactor']);
            Route::delete('/factors/{factor}', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'deleteFactor']);
            Route::apiResource('/formulas', \App\Http\Controllers\Api\Admin\CalculationFormulaController::class);
            Route::apiResource('/units', \App\Http\Controllers\Api\Admin\AdminUnitController::class);
            Route::apiResource('/scopes', \App\Http\Controllers\Api\Admin\AdminScopeController::class);
        });
    });

    // Context-Aware Routes — segmentadas por rol según Matriz de Permisos v2
    Route::middleware(['context.aware'])->group(function () {

        // ── Datos compartidos de lectura: todos los roles ──────────────────
        Route::get('/companies', [App\Http\Controllers\Api\CompanyController::class, 'index']);
        Route::get('/companies/{company}/periods', [App\Http\Controllers\Api\CompanyController::class, 'periods']);
        Route::get('/companies/{company}/units', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'index']);
        Route::get('/dictionaries/factors', [App\Http\Controllers\Api\MasterDataController::class, 'emissionFactors']);
        Route::get('/dictionaries/questionnaire', [App\Http\Controllers\Api\MasterDataController::class, 'questionnaireRules']);

        // ── Telemetría: todos los roles (Técnico IoT = gestor principal) ──
        Route::middleware(['role:superadmin,admin,user,iot_tech,auditor'])->group(function () {
            Route::get('/telemetry/live', [App\Http\Controllers\Api\TelemetryController::class, 'live']);
            Route::get('/telemetry/history', [App\Http\Controllers\Api\TelemetryController::class, 'history']);
        });

        // ── Emisiones lectura + Reportes: roles operativos + Auditor ──────
        Route::middleware(['role:superadmin,admin,user,auditor'])->group(function () {
            Route::get('/periods/{period}/emissions', [App\Http\Controllers\Api\CarbonEmissionController::class, 'index']);
            Route::get('/companies/{company}/emissions/history', [App\Http\Controllers\Api\CarbonEmissionController::class, 'history']);
            Route::get('/companies/{company}/emissions/comparison', [App\Http\Controllers\Api\CarbonEmissionController::class, 'comparison']);
            Route::get('/reports/periods/{period}/pdf', [App\Http\Controllers\Api\ReportController::class, 'pdfSummary']);
            Route::get('/reports/periods/{period}/excel', [App\Http\Controllers\Api\ReportController::class, 'excelExport']);
            // A10: Reporte de Avance y Reporte IoT
            Route::get('/reports/periods/{period}/progress', [App\Http\Controllers\Api\ReportController::class, 'progressReport']);
            Route::get('/reports/periods/{period}/iot', [App\Http\Controllers\Api\ReportController::class, 'iotReport']);
            // Evidencias: lectura para todos los roles operativos + auditor
            Route::get('/emissions/{emission}/evidences', [App\Http\Controllers\Api\EmissionEvidenceController::class, 'index']);
            Route::get('/emissions/{emission}/evidences/{evidence}/download', [App\Http\Controllers\Api\EmissionEvidenceController::class, 'download']);
        });

        // ── Evidencias: carga (user/admin/superadmin) y eliminación ───────
        Route::middleware(['role:superadmin,admin,user'])->group(function () {
            Route::post('/emissions/{emission}/evidences', [App\Http\Controllers\Api\EmissionEvidenceController::class, 'store']);
            Route::delete('/emissions/{emission}/evidences/{evidence}', [App\Http\Controllers\Api\EmissionEvidenceController::class, 'destroy']);
        });

        // ── Emisiones escritura: roles operativos (no Auditor, no IoT) ────
        Route::middleware(['role:superadmin,admin,user'])->group(function () {
            Route::post('/periods/{period}/emissions', [App\Http\Controllers\Api\CarbonEmissionController::class, 'store']);
        });

        // ── Emisiones eliminación y validación: solo Admin y Superadmin ───
        Route::middleware(['role:superadmin,admin'])->group(function () {
            Route::delete('/emissions/{emission}', [App\Http\Controllers\Api\CarbonEmissionController::class, 'destroy']);
            // A09: revisión de calidad de datos
            Route::post('/emissions/{emission}/validate', [App\Http\Controllers\Api\CarbonEmissionController::class, 'validate']);
            Route::post('/emissions/{emission}/flag', [App\Http\Controllers\Api\CarbonEmissionController::class, 'flag']);
            Route::post('/emissions/{emission}/reset-validation', [App\Http\Controllers\Api\CarbonEmissionController::class, 'resetValidation']);
        });

        // ── Dashboard + IA + Simulador: roles operativos únicamente ───────
        Route::middleware(['role:superadmin,admin,user'])->group(function () {
            Route::get('/dashboard/summary', [App\Http\Controllers\Api\DashboardController::class, 'summary']);
            Route::get('/dashboard/trends', [App\Http\Controllers\Api\DashboardController::class, 'trends']);
            Route::get('/ai/recommendations', [\App\Http\Controllers\Api\AISidecarController::class, 'getRecommendations']);
            Route::post('/ai/chat', [\App\Http\Controllers\Api\AISidecarController::class, 'chat']);
            Route::get('/simulator/scenarios', [\App\Http\Controllers\Api\SimulatorController::class, 'index']);
            Route::post('/simulator/calculate', [\App\Http\Controllers\Api\SimulatorController::class, 'calculate']);
        });
    });
});

