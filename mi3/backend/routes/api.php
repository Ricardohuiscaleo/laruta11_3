<?php

use App\Http\Controllers\Admin\AiPromptController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\Rider\RiderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — mi3 RRHH
|--------------------------------------------------------------------------
| Prefijo: /api/v1/
| Grupos: auth (público), worker (autenticado), admin (admin)
*/

Route::prefix('v1')->group(function () {

    // ── Webhooks (autenticado por secret, no por sesión) ────────
    Route::post('webhook/venta', [\App\Http\Controllers\WebhookController::class, 'venta']);
    Route::post('webhook/stock', [\App\Http\Controllers\WebhookController::class, 'stock']);

    // ── Rendiciones (público — accesible desde link WhatsApp) ────────
    Route::get('rendicion/{token}', [\App\Http\Controllers\Admin\RendicionController::class, 'show']);
    Route::post('rendicion/{token}/aprobar', [\App\Http\Controllers\Admin\RendicionController::class, 'aprobar']);
    Route::post('rendicion/{token}/rechazar', [\App\Http\Controllers\Admin\RendicionController::class, 'rechazar']);

    // ── Auth (público) ──────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
        Route::post('logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout'])
            ->middleware('auth:sanctum');
        Route::get('me', [\App\Http\Controllers\Auth\AuthController::class, 'me'])
            ->middleware('auth:sanctum');
        Route::post('clear-session', [\App\Http\Controllers\Auth\AuthController::class, 'clearSession']);

        // Google OAuth (public, no auth required)
        Route::get('google/redirect', [\App\Http\Controllers\Auth\AuthController::class, 'googleRedirect']);
        Route::get('google/callback', [\App\Http\Controllers\Auth\AuthController::class, 'googleCallback']);
    });

    // ── Worker (trabajador autenticado) ─────────────────────────────
    Route::prefix('worker')->middleware(['auth:sanctum', 'worker'])->group(function () {
        Route::get('profile', [\App\Http\Controllers\Worker\ProfileController::class, 'index']);
        Route::get('shifts', [\App\Http\Controllers\Worker\ShiftController::class, 'index']);
        Route::get('payroll', [\App\Http\Controllers\Worker\PayrollController::class, 'index']);
        Route::get('credit', [\App\Http\Controllers\Worker\CreditController::class, 'index']);
        Route::get('credit/transactions', [\App\Http\Controllers\Worker\CreditController::class, 'transactions']);
        Route::get('attendance', [\App\Http\Controllers\Worker\AttendanceController::class, 'index']);
        Route::get('shift-swaps', [\App\Http\Controllers\Worker\ShiftSwapController::class, 'index']);
        Route::get('shift-swaps/companions', [\App\Http\Controllers\Worker\ShiftSwapController::class, 'companions']);
        Route::post('shift-swaps', [\App\Http\Controllers\Worker\ShiftSwapController::class, 'store']);
        Route::get('notifications', [\App\Http\Controllers\Worker\NotificationController::class, 'index']);
        Route::patch('notifications/{id}/read', [\App\Http\Controllers\Worker\NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [\App\Http\Controllers\Worker\NotificationController::class, 'markAllAsRead']);

        // Loans (adelantos de sueldo)
        Route::get('loans', [\App\Http\Controllers\Worker\LoanController::class, 'index']);
        Route::get('loans/info', [\App\Http\Controllers\Worker\LoanController::class, 'info']);
        Route::post('loans', [\App\Http\Controllers\Worker\LoanController::class, 'store']);

        // Dashboard summary
        Route::get('dashboard-summary', [\App\Http\Controllers\Worker\DashboardController::class, 'index']);

        // Replacements
        Route::get('replacements', [\App\Http\Controllers\Worker\ReplacementController::class, 'index']);

        // Push notifications
        Route::post('push/subscribe', [\App\Http\Controllers\Worker\PushController::class, 'subscribe']);

        // Checklists
        Route::get('checklists', [\App\Http\Controllers\Worker\ChecklistController::class, 'index']);
        Route::post('checklists/{id}/items/{itemId}/complete', [\App\Http\Controllers\Worker\ChecklistController::class, 'completeItem']);
        Route::post('checklists/{id}/items/{itemId}/photo', [\App\Http\Controllers\Worker\ChecklistController::class, 'uploadPhoto']);
        Route::post('checklists/{id}/items/{itemId}/verify-cash', [\App\Http\Controllers\Worker\ChecklistController::class, 'verifyCash']);
        Route::post('checklists/{id}/complete', [\App\Http\Controllers\Worker\ChecklistController::class, 'complete']);
        Route::get('checklists/virtual', [\App\Http\Controllers\Worker\ChecklistController::class, 'virtual']);
        Route::post('checklists/virtual/{id}/complete', [\App\Http\Controllers\Worker\ChecklistController::class, 'completeVirtual']);
    });

    // ── Admin (administrador/dueño) ─────────────────────────────────
    Route::prefix('admin')->middleware(['auth:sanctum', 'worker', 'admin'])->group(function () {
        // Compras
        Route::get('compras/items', [\App\Http\Controllers\Admin\CompraController::class, 'items']);
        Route::get('compras/proveedores', [\App\Http\Controllers\Admin\CompraController::class, 'proveedores']);
        Route::post('compras/ingrediente', [\App\Http\Controllers\Admin\CompraController::class, 'crearIngrediente']);
        Route::post('compras/upload-temp', [\App\Http\Controllers\Admin\CompraController::class, 'uploadTemp']);
        Route::post('compras/extract', [\App\Http\Controllers\Admin\ExtraccionController::class, 'extract']);
        Route::post('compras/extract-pipeline', [\App\Http\Controllers\Admin\ExtraccionController::class, 'extractPipeline']);
        Route::get('compras/extraction-logs', [\App\Http\Controllers\Admin\ExtraccionController::class, 'extractionLogs']);
        Route::get('compras/extraction-logs/{id}', [\App\Http\Controllers\Admin\ExtraccionController::class, 'extractionLogDetail']);
        Route::get('compras/ai-budget', [\App\Http\Controllers\Admin\ExtraccionController::class, 'aiBudget']);
        Route::get('compras/extraction-quality', [\App\Http\Controllers\Admin\ExtraccionController::class, 'quality']);
        Route::post('compras/pipeline/run', [\App\Http\Controllers\Admin\ExtraccionController::class, 'runPipeline']);
        Route::get('compras/pipeline/report', [\App\Http\Controllers\Admin\ExtraccionController::class, 'pipelineReport']);
        Route::post('compras/{id}/imagen', [\App\Http\Controllers\Admin\CompraController::class, 'uploadImagen']);

        // AI Prompts (Compras pipeline)
        Route::get('compras/ai-prompts', [AiPromptController::class, 'index']);
        Route::get('compras/ai-prompts/{id}', [AiPromptController::class, 'show']);
        Route::put('compras/ai-prompts/{id}', [AiPromptController::class, 'update']);
        Route::post('compras/ai-prompts/{id}/revert/{versionId}', [AiPromptController::class, 'revert']);

        Route::apiResource('compras', \App\Http\Controllers\Admin\CompraController::class)->only(['index', 'store', 'show', 'destroy']);

        // Rendiciones (admin: create, list, preview)
        Route::get('rendiciones', [\App\Http\Controllers\Admin\RendicionController::class, 'index']);
        Route::get('rendiciones/preview', [\App\Http\Controllers\Admin\RendicionController::class, 'preview']);
        Route::post('rendiciones', [\App\Http\Controllers\Admin\RendicionController::class, 'store']);
        Route::delete('rendiciones/{id}', [\App\Http\Controllers\Admin\RendicionController::class, 'anular']);

        // Stock
        Route::get('stock', [\App\Http\Controllers\Admin\StockController::class, 'index']);
        Route::get('stock/bebidas', [\App\Http\Controllers\Admin\StockController::class, 'bebidas']);
        Route::post('stock/ajuste-masivo', [\App\Http\Controllers\Admin\StockController::class, 'ajusteMasivo']);
        Route::post('stock/preview-ajuste', [\App\Http\Controllers\Admin\StockController::class, 'previewAjuste']);
        Route::patch('stock/{id}', [\App\Http\Controllers\Admin\StockController::class, 'update']);
        Route::post('stock/consumir', [\App\Http\Controllers\Admin\StockController::class, 'consumir']);

        // KPIs
        Route::get('kpis', [\App\Http\Controllers\Admin\KpiController::class, 'index']);
        Route::get('kpis/historial-saldo', [\App\Http\Controllers\Admin\KpiController::class, 'historialSaldo']);
        Route::get('kpis/proyeccion', [\App\Http\Controllers\Admin\KpiController::class, 'proyeccion']);
        Route::get('kpis/rendiciones', [\App\Http\Controllers\Admin\KpiController::class, 'rendiciones']);
        Route::get('kpis/precio-historico/{id}', [\App\Http\Controllers\Admin\KpiController::class, 'precioHistorico']);

        // Personal
        Route::get('personal', [\App\Http\Controllers\Admin\PersonalController::class, 'index']);
        Route::post('personal', [\App\Http\Controllers\Admin\PersonalController::class, 'store']);
        Route::put('personal/{id}', [\App\Http\Controllers\Admin\PersonalController::class, 'update']);
        Route::patch('personal/{id}/toggle', [\App\Http\Controllers\Admin\PersonalController::class, 'toggle']);
        Route::patch('personal/{id}/rotate-foto', [\App\Http\Controllers\Admin\PersonalController::class, 'rotateFoto']);

        // Turnos
        Route::get('shifts', [\App\Http\Controllers\Admin\ShiftController::class, 'index']);
        Route::post('shifts', [\App\Http\Controllers\Admin\ShiftController::class, 'store']);
        Route::delete('shifts/{id}', [\App\Http\Controllers\Admin\ShiftController::class, 'destroy']);

        // Nómina
        Route::get('payroll', [\App\Http\Controllers\Admin\PayrollController::class, 'index']);
        Route::post('payroll/payments', [\App\Http\Controllers\Admin\PayrollController::class, 'storePayment']);
        Route::put('payroll/budget', [\App\Http\Controllers\Admin\PayrollController::class, 'updateBudget']);
        Route::post('payroll/send-liquidacion', [\App\Http\Controllers\Admin\PayrollController::class, 'sendLiquidacion']);
        Route::post('payroll/send-all', [\App\Http\Controllers\Admin\PayrollController::class, 'sendAll']);

        // Ajustes
        Route::get('adjustments', [\App\Http\Controllers\Admin\AdjustmentController::class, 'index']);
        Route::get('adjustments/categories', [\App\Http\Controllers\Admin\AdjustmentController::class, 'categories']);
        Route::post('adjustments', [\App\Http\Controllers\Admin\AdjustmentController::class, 'store']);
        Route::delete('adjustments/{id}', [\App\Http\Controllers\Admin\AdjustmentController::class, 'destroy']);

        // Créditos R11
        Route::get('credits', [\App\Http\Controllers\Admin\CreditController::class, 'index']);
        Route::post('credits/{id}/approve', [\App\Http\Controllers\Admin\CreditController::class, 'approve']);
        Route::post('credits/{id}/reject', [\App\Http\Controllers\Admin\CreditController::class, 'reject']);
        Route::post('credits/{id}/manual-payment', [\App\Http\Controllers\Admin\CreditController::class, 'manualPayment']);

        // Solicitudes de cambio
        Route::get('shift-swaps', [\App\Http\Controllers\Admin\ShiftSwapController::class, 'index']);
        Route::post('shift-swaps/{id}/approve', [\App\Http\Controllers\Admin\ShiftSwapController::class, 'approve']);
        Route::post('shift-swaps/{id}/reject', [\App\Http\Controllers\Admin\ShiftSwapController::class, 'reject']);

        // Loans
        Route::get('loans', [\App\Http\Controllers\Admin\LoanController::class, 'index']);
        Route::post('loans/{id}/approve', [\App\Http\Controllers\Admin\LoanController::class, 'approve']);
        Route::post('loans/{id}/reject', [\App\Http\Controllers\Admin\LoanController::class, 'reject']);

        // Checklists
        Route::get('checklists', [\App\Http\Controllers\Admin\ChecklistController::class, 'index']);
        Route::get('checklists/attendance', [\App\Http\Controllers\Admin\ChecklistController::class, 'attendance']);
        Route::get('checklists/ideas', [\App\Http\Controllers\Admin\ChecklistController::class, 'ideas']);
        Route::get('checklists/ai-photos', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiPhotos']);
        Route::post('checklists/ai-feedback', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiFeedback']);
        Route::post('checklists/ai-test', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiTest']);
        Route::get('checklists/ai-prompts', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiPrompts']);
        Route::put('checklists/ai-prompts/{id}', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiPromptsUpdate']);
        Route::post('checklists/ai-prompts/{id}/activate', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiPromptsActivate']);
        Route::post('checklists/ai-prompts/{id}/generate-candidate', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiPromptsGenerateCandidate']);
        Route::delete('checklists/ai-prompts/{id}', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiPromptsDelete']);
        Route::get('checklists/ai-tasks', [\App\Http\Controllers\Admin\ChecklistController::class, 'aiTasks']);
        Route::get('checklists/{id}', [\App\Http\Controllers\Admin\ChecklistController::class, 'show']);

        // Cronjobs status (Coolify scheduled tasks)
        Route::get('cronjobs', [\App\Http\Controllers\Admin\CronjobController::class, 'index']);

        // Dashboard KPIs (ventas, compras, nómina)
        Route::get('dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);

        // Recetas (recipe management)
        // Static routes MUST come before {productId} to avoid route conflicts
        Route::post('recetas/bulk-adjustment/preview', [\App\Http\Controllers\RecipeController::class, 'bulkPreview']);
        Route::post('recetas/bulk-adjustment', [\App\Http\Controllers\RecipeController::class, 'bulkApply']);
        Route::post('recetas/replace-ingredient/preview', [\App\Http\Controllers\RecipeController::class, 'replacePreview']);
        Route::post('recetas/replace-ingredient', [\App\Http\Controllers\RecipeController::class, 'replaceApply']);
        Route::get('recetas/catalogo', [\App\Http\Controllers\RecipeController::class, 'catalogo']);
        Route::get('recetas/recommendations', [\App\Http\Controllers\RecipeController::class, 'recommendations']);
        Route::get('recetas/audit/export', [\App\Http\Controllers\RecipeController::class, 'auditExport']);
        Route::get('recetas/audit', [\App\Http\Controllers\RecipeController::class, 'audit']);

        Route::get('recetas', [\App\Http\Controllers\RecipeController::class, 'index']);
        Route::get('recetas/{productId}', [\App\Http\Controllers\RecipeController::class, 'show']);
        Route::post('recetas/{productId}', [\App\Http\Controllers\RecipeController::class, 'store']);
        Route::put('recetas/{productId}', [\App\Http\Controllers\RecipeController::class, 'update']);
        Route::put('recetas/{productId}/producto', [\App\Http\Controllers\RecipeController::class, 'updateProduct']);
        Route::post('recetas/{productId}/imagen', [\App\Http\Controllers\RecipeController::class, 'uploadProductImage']);
        Route::delete('recetas/{productId}/{ingredientId}', [\App\Http\Controllers\RecipeController::class, 'destroyIngredient']);

        // Sub-recetas (ingredient recipes — composite ingredients)
        Route::get('ingredient-recipes', [\App\Http\Controllers\Admin\IngredientRecipeController::class, 'index']);
        Route::get('ingredient-recipes/{ingredientId}', [\App\Http\Controllers\Admin\IngredientRecipeController::class, 'show']);
        Route::post('ingredient-recipes/{ingredientId}', [\App\Http\Controllers\Admin\IngredientRecipeController::class, 'store']);
        Route::delete('ingredient-recipes/{ingredientId}', [\App\Http\Controllers\Admin\IngredientRecipeController::class, 'destroy']);
    });
});

// ── Delivery Tracking — Admin ───────────────────────────────────────
Route::middleware(['auth:sanctum', 'worker', 'admin'])->prefix('v1/admin/delivery')->group(function () {
    Route::get('/orders', [DeliveryController::class, 'index']);
    Route::patch('/orders/{id}/status', [DeliveryController::class, 'updateStatus']);
    Route::post('/orders/{id}/assign-rider', [DeliveryController::class, 'assignRider']);
    Route::get('/riders', [DeliveryController::class, 'riders']);
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::get('/settlements/{id}', [SettlementController::class, 'show']);
    Route::post('/settlements/{id}/voucher', [SettlementController::class, 'uploadVoucher']);
    Route::post('/simulate', [\App\Http\Controllers\Admin\DeliveryController::class, 'simulate']);
});

// ── Delivery Tracking — Rider ───────────────────────────────────────
Route::middleware(['auth:sanctum', 'worker'])->prefix('v1/rider')->group(function () {
    Route::post('/location', [RiderController::class, 'updateLocation']);
    Route::get('/current-assignment', [RiderController::class, 'currentAssignment']);
    Route::patch('/current-assignment/status', [RiderController::class, 'updateAssignmentStatus']);
});

// ── Delivery Tracking — Público (sin auth) ──────────────────────────
Route::prefix('v1/public')->group(function () {
    Route::get('/orders/{orderNumber}/tracking', [TrackingController::class, 'show']);
});

// ── Public checklist endpoints (para caja3, sin auth) ───────────────
Route::prefix('v1/public/checklists')->group(function () {
    Route::get('today', [\App\Http\Controllers\Public\ChecklistController::class, 'today']);
    Route::post('{id}/items/{itemId}/complete', [\App\Http\Controllers\Public\ChecklistController::class, 'completeItem']);
    Route::post('{id}/items/{itemId}/photo', [\App\Http\Controllers\Public\ChecklistController::class, 'uploadPhoto']);
    Route::post('{id}/items/{itemId}/verify-cash', [\App\Http\Controllers\Public\ChecklistController::class, 'verifyCash']);
    Route::post('{id}/complete', [\App\Http\Controllers\Public\ChecklistController::class, 'complete']);
});

// ── Delivery Tracking — Webhook desde caja3 ─────────────────────────
Route::post('v1/webhooks/order-status', [\App\Http\Controllers\Webhook\OrderStatusWebhookController::class, 'handle']);
