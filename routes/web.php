<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Root redirect to Admin Dashboard
Route::get('/', function () {
    return redirect('/admin/dashboard');
});

Route::get('/dashboard', function () {
    return redirect('/admin/dashboard');
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Portal Routes (Protected by Auth Middleware)
Route::prefix('admin')->middleware(['auth'])->group(function () {
    // User & Session Management
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users');
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/sessions/{sessionId}/revoke', [\App\Http\Controllers\Admin\UserController::class, 'revokeSession'])->name('admin.sessions.revoke');
    Route::post('/sessions/revoke-others', [\App\Http\Controllers\Admin\UserController::class, 'revokeOtherSessions'])->name('admin.sessions.revoke-others');

    // System Settings & Release Management
    Route::get('/settings', [\App\Http\Controllers\Admin\AppSettingController::class, 'index'])->name('admin.settings');
    Route::post('/settings', [\App\Http\Controllers\Admin\AppSettingController::class, 'update'])->name('admin.settings.update');

    // Dashboard & Live Punches
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/sync-adms', [DashboardController::class, 'syncAdms'])->name('admin.sync-adms');

    // Devices & QR Onboarding
    Route::get('/devices', [DeviceController::class, 'index'])->name('admin.devices');
    Route::post('/devices/{device}/approve', [DeviceController::class, 'approve'])->name('admin.devices.approve');
    Route::post('/devices/{device}/suspend', [DeviceController::class, 'suspend'])->name('admin.devices.suspend');
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('admin.devices.destroy');
    Route::post('/devices/generate-qr', [DeviceController::class, 'generateQr'])->name('admin.devices.generate-qr');

    // Employees & Shifts
    Route::get('/employees', [EmployeeController::class, 'index'])->name('admin.employees');
    Route::get('/employees/search', [EmployeeController::class, 'search'])->name('admin.employees.search');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('admin.employees.show');
    Route::post('/employees/{employee}/shift', [EmployeeController::class, 'updateShift'])->name('admin.employees.shift');
    Route::post('/employees/{employee}/role', [EmployeeController::class, 'updateRole'])->name('admin.employees.role');

    // Leaves & Absence Management
    Route::get('/leaves', [\App\Http\Controllers\Admin\LeaveController::class, 'index'])->name('admin.leaves');
    Route::post('/leaves', [\App\Http\Controllers\Admin\LeaveController::class, 'store'])->name('admin.leaves.store');
    Route::post('/leaves/{leave}/approve', [\App\Http\Controllers\Admin\LeaveController::class, 'approve'])->name('admin.leaves.approve');
    Route::post('/leaves/{leave}/reject', [\App\Http\Controllers\Admin\LeaveController::class, 'reject'])->name('admin.leaves.reject');

    // Attendance Corrections & Missed Punches
    Route::get('/corrections', [\App\Http\Controllers\Admin\CorrectionController::class, 'index'])->name('admin.corrections');
    Route::post('/corrections/{correction}/approve', [\App\Http\Controllers\Admin\CorrectionController::class, 'approve'])->name('admin.corrections.approve');
    Route::post('/corrections/{correction}/reject', [\App\Http\Controllers\Admin\CorrectionController::class, 'reject'])->name('admin.corrections.reject');
    Route::post('/corrections/manual-punch', [\App\Http\Controllers\Admin\CorrectionController::class, 'manualPunch'])->name('admin.corrections.manual-punch');

    // Audit Trail & Logs
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('admin.audit-logs');

    // Branches & Geofence
    Route::get('/branches', [BranchController::class, 'index'])->name('admin.branches');
    Route::post('/branches', [BranchController::class, 'store'])->name('admin.branches.store');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('admin.branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('admin.branches.destroy');
    Route::post('/branches/{branch}/checkpoints', [BranchController::class, 'storeCheckpoint'])->name('admin.branches.checkpoints.store');
    Route::delete('/checkpoints/{checkpoint}', [BranchController::class, 'destroyCheckpoint'])->name('admin.checkpoints.destroy');

    // Shifts & Overtime
    Route::get('/shifts', [ShiftController::class, 'index'])->name('admin.shifts');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('admin.shifts.store');
    Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('admin.shifts.update');
    Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('admin.shifts.destroy');

    // Company Holidays Calendar
    Route::get('/holidays', [\App\Http\Controllers\Admin\HolidayController::class, 'index'])->name('admin.holidays');
    Route::post('/holidays', [\App\Http\Controllers\Admin\HolidayController::class, 'store'])->name('admin.holidays.store');
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\Admin\HolidayController::class, 'destroy'])->name('admin.holidays.destroy');
    Route::post('/holidays/import-national', [\App\Http\Controllers\Admin\HolidayController::class, 'importNationalHolidays'])->name('admin.holidays.import-national');

    // Attendance Reports & CSV Export
    Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('/reports/export-csv', [ReportController::class, 'exportCsv'])->name('admin.reports.export-csv');

    // Field Operations: Customers / Visitable Locations
    Route::get('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('admin.customers');
    Route::post('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'store'])->name('admin.customers.store');
    Route::put('/customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'update'])->name('admin.customers.update');
    Route::delete('/customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'destroy'])->name('admin.customers.destroy');
    Route::post('/customers/import-csv', [\App\Http\Controllers\Admin\CustomerController::class, 'importCsv'])->name('admin.customers.import-csv');

    // Field Operations: Live Visits & Tracker
    Route::get('/field-visits', [\App\Http\Controllers\Admin\FieldVisitController::class, 'index'])->name('admin.field-visits');
    Route::get('/field-visits/live', [\App\Http\Controllers\Admin\FieldVisitController::class, 'liveData'])->name('admin.field-visits.live');
    Route::get('/field-visits/export', [\App\Http\Controllers\Admin\FieldVisitController::class, 'exportCsv'])->name('admin.field-visits.export');
    Route::get('/field-visits/{fieldVisit}/breadcrumbs', [\App\Http\Controllers\Admin\FieldVisitController::class, 'breadcrumbs'])->name('admin.field-visits.breadcrumbs');

    // Field Operations: Tasks & Canvass Plans
    Route::get('/field-tasks', [\App\Http\Controllers\Admin\FieldTaskController::class, 'index'])->name('admin.field-tasks');
    Route::post('/field-tasks', [\App\Http\Controllers\Admin\FieldTaskController::class, 'store'])->name('admin.field-tasks.store');
    Route::put('/field-tasks/{task}', [\App\Http\Controllers\Admin\FieldTaskController::class, 'update'])->name('admin.field-tasks.update');
    Route::delete('/field-tasks/{task}', [\App\Http\Controllers\Admin\FieldTaskController::class, 'destroy'])->name('admin.field-tasks.destroy');
    Route::post('/canvass-plans', [\App\Http\Controllers\Admin\FieldTaskController::class, 'storeCanvassPlan'])->name('admin.canvass-plans.store');

    // Odoo CRM / ERP Sync
    Route::get('/odoo-sync', [\App\Http\Controllers\Admin\OdooSyncController::class, 'index'])->name('admin.odoo-sync');
    Route::post('/odoo-sync/settings', [\App\Http\Controllers\Admin\OdooSyncController::class, 'updateSettings'])->name('admin.odoo-sync.settings');
    Route::post('/odoo-sync/test', [\App\Http\Controllers\Admin\OdooSyncController::class, 'testConnection'])->name('admin.odoo-sync.test');
    Route::post('/odoo-sync/trigger', [\App\Http\Controllers\Admin\OdooSyncController::class, 'triggerSync'])->name('admin.odoo-sync.trigger');
});

require __DIR__.'/auth.php';
