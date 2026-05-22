<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowRunController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', fn () => response()->json([
    'status'    => 'ok',
    'timestamp' => now()->toISOString(),
]));

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    });
});

Route::middleware(['auth:api'])->group(function () {

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/health', [DashboardController::class, 'health'])->name('dashboard.health');
        Route::get('/recent-runs', [DashboardController::class, 'recentRuns'])->name('dashboard.recent-runs');
    });

    // Workflows
    Route::apiResource('workflows', WorkflowController::class);
    Route::prefix('workflows/{workflow}')->group(function () {
        Route::post('/trigger', [WorkflowController::class, 'trigger'])->name('workflows.trigger');
        Route::get('/runs', [WorkflowController::class, 'runs'])->name('workflows.runs');
        Route::get('/versions', [WorkflowController::class, 'versions'])->name('workflows.versions');
        Route::post('/rollback', [WorkflowController::class, 'rollback'])->name('workflows.rollback');
    });

    // Runs
    Route::prefix('runs/{run}')->group(function () {
        Route::get('/', [WorkflowRunController::class, 'show'])->name('runs.show');
        Route::post('/cancel', [WorkflowRunController::class, 'cancel'])->name('runs.cancel');
        Route::get('/logs', [WorkflowRunController::class, 'logs'])->name('runs.logs');
        Route::post('/analyze-failure', [WorkflowRunController::class, 'analyzeFailure'])->name('runs.analyze');
    });

    // AI Features
    Route::prefix('ai')->group(function () {
        Route::post('/generate-workflow', [AIController::class, 'generateWorkflow'])->name('ai.generate');
        Route::post('/validate-definition', [AIController::class, 'validateDefinition'])->name('ai.validate');
    });
});
