<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — mi3 RRHH
|--------------------------------------------------------------------------
| Prefijo: /api/v1/
| Grupos: auth (público), worker (autenticado), admin (admin)
*/

Route::prefix('v1')->group(function () {

    // ── Auth (público) ──────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
        Route::post('logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout'])
            ->middleware('auth:sanctum');
        Route::get('me', [\App\Http\Controllers\Auth\AuthController::class, 'me'])
            ->middleware('auth:sanctum');

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

        // Loans
        Route::get('loans', [\App\Http\Controllers\Worker\LoanController::class, 'index']);
        Route::post('loans', [\App\Http\Controllers\Worker\LoanController::class, 'store']);

        // Dashboard summary
        Route::get('dashboard-summary', [\App\Http\Controllers\Worker\DashboardController::class, 'index']);

        // Replacements
        Route::get('replacements', [\App\Http\Controllers\Worker\ReplacementController::class, 'index']);

        // Push notifications
        Route::post('push/subscribe', [\App\Http\Controllers\Worker\PushController::class, 'subscribe']);
    });

    // ── Admin (administrador/dueño) ─────────────────────────────────
    Route::prefix('admin')->middleware(['auth:sanctum', 'worker', 'admin'])->group(function () {
        // Personal
        Route::get('personal', [\App\Http\Controllers\Admin\PersonalController::class, 'index']);
        Route::post('personal', [\App\Http\Controllers\Admin\PersonalController::class, 'store']);
        Route::put('personal/{id}', [\App\Http\Controllers\Admin\PersonalController::class, 'update']);
        Route::patch('personal/{id}/toggle', [\App\Http\Controllers\Admin\PersonalController::class, 'toggle']);

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
    });
});
