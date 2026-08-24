<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Controllers\Api\v1\AppStatusController;
use App\Http\Controllers\Api\v1\DeviceConfigController;
use App\Http\Controllers\Api\v1\DeviceOnboardController;
use App\Http\Controllers\Api\v1\DeviceDiagnosticController;
use App\Http\Controllers\Api\v1\DeviceFcmController;
use App\Http\Controllers\Api\v1\PunchController;
use App\Http\Controllers\Api\v1\PunchBatchController;
use App\Http\Controllers\Api\v1\PunchSelfieController;
use App\Http\Controllers\Api\v1\PunchTypeController;
use App\Http\Controllers\Api\v1\PunchHistoryController;
use App\Http\Controllers\Api\v1\FieldVisitApiController;
use App\Http\Controllers\Api\v1\FieldTaskApiController;
use App\Http\Controllers\Api\v1\CustomerApiController;

// Public Health & Version check (no API key required)
Route::prefix('v1')->group(function () {
    Route::get('/app-status', [AppStatusController::class, 'show']);
    Route::get('/app/version-check', [\App\Http\Controllers\Api\v1\AppUpdateController::class, 'check']);
});

// Authenticated Mobile API v1 routes (Requires X-API-Key)
Route::prefix('v1')->middleware(AuthenticateApiKey::class)->group(function () {
    // Device & Config
    Route::get('/device-config', [DeviceConfigController::class, 'show']);
    Route::post('/device-onboard', [DeviceOnboardController::class, 'store']);
    Route::get('/device/diagnostics', [DeviceDiagnosticController::class, 'show']);
    Route::post('/device/fcm-token', [DeviceFcmController::class, 'update']);

    // Attendance & Punches
    Route::post('/punch', [PunchController::class, 'store']);
    Route::post('/punch/batch', [PunchBatchController::class, 'store']);
    Route::post('/punch/selfie', [PunchSelfieController::class, 'store']);
    Route::get('/punch-types', [PunchTypeController::class, 'index']);
    Route::get('/punch-history', [PunchHistoryController::class, 'index']);

    // Field Workforce & Visits
    Route::post('/field-visit/check-in', [FieldVisitApiController::class, 'checkIn']);
    Route::post('/field-visit/check-out', [FieldVisitApiController::class, 'checkOut']);
    Route::post('/field-visit/{id}/photo', [FieldVisitApiController::class, 'uploadPhoto']);
    Route::post('/field-visit/{id}/breadcrumbs', [FieldVisitApiController::class, 'recordBreadcrumbs']);
    Route::get('/field-visit/{id}/breadcrumbs', [FieldVisitApiController::class, 'getBreadcrumbs']);
    Route::get('/field-visits', [FieldVisitApiController::class, 'history']);

    // Field Tasks & Canvassing Plans
    Route::get('/field-tasks', [FieldTaskApiController::class, 'index']);
    Route::post('/field-tasks/{id}/start', [FieldTaskApiController::class, 'start']);
    Route::post('/field-tasks/{id}/complete', [FieldTaskApiController::class, 'complete']);

    // Customers & Canvass Route
    Route::get('/customers', [CustomerApiController::class, 'index']);
    Route::get('/canvass-plan/today', [CustomerApiController::class, 'todayCanvassPlan']);

    // Leave & Late Arrival Permit Hub
    Route::get('/leaves/balance', [\App\Http\Controllers\Api\v1\LeaveApiController::class, 'getBalance']);
    Route::get('/leaves/history', [\App\Http\Controllers\Api\v1\LeaveApiController::class, 'getHistory']);
    Route::post('/leaves/request', [\App\Http\Controllers\Api\v1\LeaveApiController::class, 'submitRequest']);
});

