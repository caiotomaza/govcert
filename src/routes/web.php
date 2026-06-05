<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuditOperationsController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Web\ApiTokenController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuditLogDetailController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\TokenGovernanceController;
use Illuminate\Support\Facades\Route;

// ─── Visitantes (não autenticados) ────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Redefinição de senha via link enviado por e-mail
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->name('password.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Área autenticada (verifica status ativo em TODA rota) ────────────────
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Logs de auditoria (Admin e Auditor)
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/logs', [AuditLogController::class, 'index'])->name('index');
        Route::get('/logs/export/csv', [AuditLogController::class, 'exportCsv'])->name('export.csv');
        Route::post('/logs/export/pdf', [AuditLogController::class, 'exportPdf'])->name('export.pdf');
        // Detalhe mascarado (AJAX) — dados originais nunca saem do banco
        Route::get('/logs/{log}/detail', [AuditLogDetailController::class, 'show'])->name('logs.detail');
    });

    // Governança de tokens
    Route::prefix('tokens')->name('tokens.')->group(function () {
        Route::get('/', [TokenGovernanceController::class, 'index'])->name('index');
        Route::post('/alerts', [TokenGovernanceController::class, 'storeAlert'])->name('alerts.store');
        Route::put('/alerts/{alert}', [TokenGovernanceController::class, 'updateAlert'])->name('alerts.update');
        Route::post('/alerts/{alert}/toggle', [TokenGovernanceController::class, 'toggleAlert'])->name('alerts.toggle');
        Route::delete('/alerts/{alert}', [TokenGovernanceController::class, 'destroyAlert'])->name('alerts.destroy');
    });

    // Meu Perfil (Admin e Auditor)
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password');

        // Autoatendimento de API Token da extensão (sem terminal)
        Route::get('/api-tokens', [ApiTokenController::class, 'show'])->name('tokens');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
        Route::delete('/api-tokens', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');
    });

    // Administração de Usuários (somente Admin)
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{user}/reset-link', [UserController::class, 'sendResetLink'])->name('users.reset-link');

        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

        // Operações manuais de auditoria
        Route::post('/audit/reprocess', [AuditOperationsController::class, 'reprocess'])->name('audit.reprocess');

        // Configurações do GovCert
        Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/ai-provider', [SystemSettingsController::class, 'updateAiProvider'])->name('settings.ai-provider.update');
        Route::post('/settings/ai-provider/test', [SystemSettingsController::class, 'testAiProvider'])->name('settings.ai-provider.test');
        Route::post('/settings/logout-all', [SystemSettingsController::class, 'logoutAll'])->name('settings.logout-all');
    });
});
