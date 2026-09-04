<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes (Must be logged in)
Route::middleware('auth')->group(function () {
    // Accessible by all authenticated roles (Admin & Karyawan)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Admin-only Routes (Karyawan restricted)
    Route::middleware('role:admin')->group(function () {
        Route::get('/deliveries', [DashboardController::class, 'deliveries'])->name('deliveries');
        Route::get('/bot-control', [DashboardController::class, 'botControl'])->name('bot-control');
        Route::get('/history', [DashboardController::class, 'history'])->name('history');
        Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
    });
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/telemetry', [TelemetryController::class, 'getTelemetry'])->name('telemetry');
    Route::post('/robots/{robot}/telemetry', [TelemetryController::class, 'updateRobot'])->name('robots.telemetry');
    Route::post('/deliveries', [TelemetryController::class, 'startDelivery'])->name('deliveries.start');
    Route::put('/deliveries/{delivery}/complete', [TelemetryController::class, 'completeDelivery'])->name('deliveries.complete');
    Route::post('/reports', [TelemetryController::class, 'reportIncident'])->name('reports.create');
    Route::put('/reports/{report}/resolve', [TelemetryController::class, 'resolveIncident'])->name('reports.resolve');
    Route::post('/robots/{robot}/simulate-issue', [TelemetryController::class, 'simulateIssue'])->name('robots.simulate-issue');
    Route::post('/robots/{robot}/fix', [TelemetryController::class, 'fixRobot'])->name('robots.fix');
    Route::post('/system/reset', [TelemetryController::class, 'resetSystem'])->name('system.reset');
    Route::post('/system/autopilot', [TelemetryController::class, 'toggleAutopilot'])->name('system.autopilot');
    Route::post('/graph/save', [TelemetryController::class, 'saveGraph'])->name('graph.save');
});
