<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

// Internal endpoint for zia-agent Python microservice (Docker network only)
Route::middleware(\App\Http\Middleware\InternalOnly::class)
    ->post('/internal/calculate', [App\Http\Controllers\Api\InternalCalculationController::class, 'calculate']);

Route::middleware(\App\Http\Middleware\InternalOnly::class)
    ->post('/internal/search-documents', [App\Http\Controllers\Api\InternalDocumentSearchController::class, 'search']);

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

        // Company Documents — insumos para el RAG del agente (subida, listado, borrado)
        Route::get('/companies/{company}/documents', [\App\Http\Controllers\Api\Admin\CompanyDocumentController::class, 'index']);
        Route::post('/companies/{company}/documents', [\App\Http\Controllers\Api\Admin\CompanyDocumentController::class, 'store']);
        Route::delete('/companies/{company}/documents/{document}', [\App\Http\Controllers\Api\Admin\CompanyDocumentController::class, 'destroy']);

        // Users Management (admin: CRU only — destroy is superadmin-only, enforced in controller)
        Route::get('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'store']);
        Route::put('/users/{user}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'destroy']);
        Route::post('/users/{id}/restore', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'restore']);
        Route::post('/users/{user}/toggle-block', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'toggleBlock']);

        // SA-17: estadísticas globales de plataforma — solo superadmin
        Route::middleware(['role:superadmin'])->get('/platform-stats', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'platformStats']);

        // Superadmin-only operations
        Route::middleware(['role:superadmin'])->group(function () {
            // Companies write — superadmin only (A02)
            Route::post('/companies', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'store']);
            Route::put('/companies/{company}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'update']);
            Route::delete('/companies/{company}', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'destroy']);
            // Aprobación metodológica ISO 14064-1 / GHG Protocol (spec 1.2.3)
            Route::post('/companies/{company}/approve-methodology', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'approveMethodology']);

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
            // Versionado de factores (spec 1.2.3), sobre la bitácora ya existente
            Route::get('/factors/{factor}/versions', [\App\Http\Controllers\Api\Admin\AdminMasterDataController::class, 'factorVersions']);
            Route::apiResource('/formulas', \App\Http\Controllers\Api\Admin\CalculationFormulaController::class);
            Route::apiResource('/units', \App\Http\Controllers\Api\Admin\AdminUnitController::class);
            Route::post('/units/{unit}/toggle', [\App\Http\Controllers\Api\Admin\AdminUnitController::class, 'toggle']);
            Route::apiResource('/scopes', \App\Http\Controllers\Api\Admin\AdminScopeController::class);
            // Catálogo global de tags (spec 1.2.3: "Diseñar catálogos globales: sectores, tags...")
            Route::apiResource('/tags', \App\Http\Controllers\Api\Admin\AdminTagController::class);
            Route::post('/tags/{tag}/toggle', [\App\Http\Controllers\Api\Admin\AdminTagController::class, 'toggle']);

            // Acceso del Auditor externo a un período específico (spec 1.2.3)
            Route::get('/auditor-assignments', [\App\Http\Controllers\Api\Admin\AdminAuditorAssignmentController::class, 'index']);
            Route::post('/auditor-assignments', [\App\Http\Controllers\Api\Admin\AdminAuditorAssignmentController::class, 'store']);
            Route::delete('/auditor-assignments/{assignment}', [\App\Http\Controllers\Api\Admin\AdminAuditorAssignmentController::class, 'destroy']);

            // SA-15: ciclo de vida de períodos
            Route::post('/periods/{period}/review', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'sendToReview']);
            Route::post('/periods/{period}/archive', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'archivePeriod']);

            // SA-12: Dashboard IoT global
            Route::get('/iot-devices', [\App\Http\Controllers\Api\Admin\AdminIotController::class, 'index']);

            // SA-11: Reporte PDF global multiorganización
            Route::get('/reports/platform', [\App\Http\Controllers\Api\Admin\AdminCompanyController::class, 'platformReport']);

            // SA-10: Gestión de cuestionarios Smart Intake
            Route::get('/questionnaires', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'index']);
            Route::post('/questionnaires', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'store']);
            Route::get('/questionnaires/{template}', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'show']);
            Route::put('/questionnaires/{template}', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'update']);
            Route::delete('/questionnaires/{template}', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'destroy']);
            Route::post('/questionnaires/{template}/publish', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'publish']);
            Route::post('/questionnaires/{template}/archive', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'archive']);
            Route::post('/questionnaires/{template}/version', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'newVersion']);
            Route::post('/questionnaires/{template}/questions', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'storeQuestion']);
            Route::put('/questionnaires/{template}/questions/{question}', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'updateQuestion']);
            Route::delete('/questionnaires/{template}/questions/{question}', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'destroyQuestion']);
            Route::post('/questionnaires/{template}/questions/reorder', [\App\Http\Controllers\Api\Admin\AdminQuestionnaireController::class, 'reorderQuestions']);
        });
    });

    // Context-Aware Routes — segmentadas por rol según Matriz de Permisos v2
    Route::middleware(['context.aware'])->group(function () {

        // ── Datos compartidos de lectura: todos los roles ──────────────────
        Route::get('/companies', [App\Http\Controllers\Api\CompanyController::class, 'index']);
        Route::get('/companies/{company}/periods', [App\Http\Controllers\Api\CompanyController::class, 'periods']);
        Route::get('/companies/{company}/units', [\App\Http\Controllers\Api\Admin\AdminOperationalUnitController::class, 'index']);
        Route::get('/companies/{company}/available-tags', [\App\Http\Controllers\Api\Admin\AdminTagController::class, 'availableForCompany']);
        Route::get('/dictionaries/factors', [App\Http\Controllers\Api\MasterDataController::class, 'emissionFactors']);
        Route::get('/dictionaries/questionnaire', [App\Http\Controllers\Api\MasterDataController::class, 'questionnaireRules']);

        // ── Telemetría: todos los roles (Técnico IoT = gestor principal) ──
        Route::middleware(['role:superadmin,admin,user,iot_tech,auditor,viewer'])->group(function () {
            Route::get('/telemetry/live', [App\Http\Controllers\Api\TelemetryController::class, 'live']);
            Route::get('/telemetry/history', [App\Http\Controllers\Api\TelemetryController::class, 'history']);
        });

        // ── Emisiones lectura + Reportes: roles operativos + Auditor + Viewer ──
        Route::middleware(['role:superadmin,admin,user,auditor,viewer'])->group(function () {
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

        // ── Dashboard: roles operativos + Auditor/Viewer (solo lectura) ───
        Route::middleware(['role:superadmin,admin,user,auditor,viewer'])->group(function () {
            Route::get('/dashboard/summary', [App\Http\Controllers\Api\DashboardController::class, 'summary']);
            Route::get('/dashboard/trends', [App\Http\Controllers\Api\DashboardController::class, 'trends']);
        });

        // ── IA + Simulador: roles operativos únicamente (no son "solo lectura") ──
        Route::middleware(['role:superadmin,admin,user'])->group(function () {
            Route::get('/ai/recommendations', [\App\Http\Controllers\Api\AISidecarController::class, 'getRecommendations']);
            Route::post('/ai/chat', [\App\Http\Controllers\Api\AISidecarController::class, 'chat']);
            Route::get('/simulator/scenarios', [\App\Http\Controllers\Api\SimulatorController::class, 'index']);
            Route::post('/simulator/calculate', [\App\Http\Controllers\Api\SimulatorController::class, 'calculate']);
        });

        // ── Dispositivos IoT (registro/config): Técnico IoT CRUD, Admin/Superadmin R+CRUD ──
        // Matriz CRUD spec 1.2.3: Dispositivo IoT = Superadmin CRUD, Técnico IoT CRUD, Admin R
        Route::middleware(['role:superadmin,admin,iot_tech'])->group(function () {
            Route::get('/companies/{company}/iot-devices', [App\Http\Controllers\Api\IotDeviceController::class, 'index']);
        });
        Route::middleware(['role:superadmin,iot_tech'])->group(function () {
            Route::post('/companies/{company}/iot-devices', [App\Http\Controllers\Api\IotDeviceController::class, 'store']);
            Route::put('/iot-devices/{device}', [App\Http\Controllers\Api\IotDeviceController::class, 'update']);
            Route::delete('/iot-devices/{device}', [App\Http\Controllers\Api\IotDeviceController::class, 'destroy']);
            Route::post('/iot-devices/{device}/calibrate', [App\Http\Controllers\Api\IotDeviceController::class, 'calibrate']);
            Route::post('/telemetry/alerts/{alert}/resolve', [App\Http\Controllers\Api\IotDeviceController::class, 'resolveAlert']);
        });

        // ── Observaciones/dictamen de Auditor externo por período ─────────
        Route::middleware(['role:superadmin,admin,auditor'])->group(function () {
            Route::get('/companies/{company}/periods/{period}/observations', [App\Http\Controllers\Api\AuditObservationController::class, 'index']);
        });
        Route::middleware(['role:superadmin,auditor'])->group(function () {
            Route::post('/companies/{company}/periods/{period}/observations', [App\Http\Controllers\Api\AuditObservationController::class, 'store']);
        });
        Route::middleware(['role:superadmin,admin'])->group(function () {
            Route::delete('/companies/{company}/periods/{period}/observations/{observation}', [App\Http\Controllers\Api\AuditObservationController::class, 'destroy']);
        });

        // ── Bitácora acotada a empresa: Auditor externo (acceso vigente) + Admin/Superadmin ──
        Route::middleware(['role:superadmin,admin,auditor'])->group(function () {
            Route::get('/companies/{company}/audit-logs', [\App\Http\Controllers\Api\Admin\AdminAuditController::class, 'companyIndex']);
        });
    });
});

