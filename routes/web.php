<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AttendanceEvidenceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeAttendanceController;
use App\Http\Controllers\HR;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\AttendanceReviewController;
use App\Http\Controllers\SelfServiceController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/activate-account', [ActivationController::class, 'show'])->middleware('throttle:30,1')->name('activation.show');
    Route::post('/activate-account', [ActivationController::class, 'store'])->middleware('throttle:10,1')->name('activation.store');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/realtime/state', WorkspaceSyncController::class)->middleware('throttle:120,1,workspace-sync:')->name('realtime.state');
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->workspaceRoute()))->name('dashboard');
    Route::get('/profile', [SelfServiceController::class, 'profile'])->name('profile');
    Route::patch('/profile', [SelfServiceController::class, 'update'])->name('profile.update');
    Route::get('/settings', [SelfServiceController::class, 'settings'])->name('settings');
    Route::get('/employee/dashboard', [DashboardController::class, 'employee'])->name('employee.dashboard');
    Route::get('/employee/profile', [SelfServiceController::class, 'profile'])->name('employee.profile');
    Route::patch('/employee/profile', [SelfServiceController::class, 'update'])->name('employee.profile.update');
    Route::get('/notifications', [WorkspaceController::class, 'notifications'])->name('notifications');
    Route::get('/leave', fn () => redirect()->route(auth()->user()->isHr() ? 'hr.leave.index' : (auth()->user()->isSupervisor() ? 'supervisor.leave.index' : 'employee.leave.index')))->name('leave');
    Route::get('/attendance', fn () => redirect()->route(auth()->user()->isHr() ? 'hr.attendance.index' : 'employee.attendance'))->name('attendance');
    Route::get('/attendance-history', fn () => redirect()->route('employee.attendance.history'))->name('attendance.history');
    Route::get('/employee/attendance', [EmployeeAttendanceController::class, 'index'])->name('employee.attendance');
    Route::get('/employee/attendance/history', [EmployeeAttendanceController::class, 'history'])->name('employee.attendance.history');
    Route::get('/employee/attendance/records/{record}', [EmployeeAttendanceController::class, 'show'])->name('attendance.show');
    Route::get('/attendance/photos/{photo}', [AttendanceEvidenceController::class, 'show'])->middleware('throttle:120,1')->name('attendance.photo');
    Route::post('/employee/attendance/captures', [EmployeeAttendanceController::class, 'start'])->middleware('throttle:20,1,attendance-start:')->name('attendance.capture.start');
    Route::post('/employee/attendance/captures/{capture}/location', [EmployeeAttendanceController::class, 'location'])->middleware('throttle:30,1,attendance-location:')->name('attendance.location');
    Route::post('/employee/attendance/captures/{capture}/submit', [EmployeeAttendanceController::class, 'submit'])->middleware('throttle:20,1,attendance-submit:')->name('attendance.submit');
    Route::get('/employee/attendance/captures/{capture}/status', [EmployeeAttendanceController::class, 'status'])->middleware('throttle:60,1,attendance-status:')->name('attendance.capture.status');
    Route::post('/employee/attendance/assistance', [EmployeeAttendanceController::class, 'assistance'])->middleware('throttle:5,1')->name('attendance.assistance');
    Route::middleware('hr')->prefix('hr')->name('hr.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'hr'])->name('dashboard');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/events/{event}/review', [AttendanceReviewController::class, 'store'])->middleware('throttle:30,1')->name('attendance.review');
        Route::post('/attendance/assistance/{assistance}', [AttendanceReviewController::class, 'assistance'])->name('attendance.assistance.review');
        Route::resource('employees', HR\EmployeeController::class)->except('destroy');
        Route::patch('/employees/{employee}/status', [HR\EmployeeController::class, 'status'])->name('employees.status');
        Route::post('/employees/{employee}/invitation', [HR\EmployeeController::class, 'resend'])->middleware('throttle:10,1')->name('employees.invitation');
        Route::resource('branches', HR\BranchController::class)->except('destroy');
        Route::post('/branch-location-search', HR\BranchLocationSearchController::class)->middleware('throttle:10,1')->name('branches.location-search');
        Route::resource('departments', HR\DepartmentController::class)->except(['destroy', 'show']);
        Route::get('/audit-logs', [HR\AuditController::class, 'index'])->name('audit');
        Route::middleware('password.confirm')->group(function () {
            Route::get('/hr-accounts', [HR\HRAccountController::class, 'index'])->name('accounts.index');
            Route::get('/hr-accounts/create', [HR\HRAccountController::class, 'create'])->name('accounts.create');
            Route::post('/hr-accounts', [HR\HRAccountController::class, 'store'])->name('accounts.store');
            Route::patch('/hr-accounts/{hrAccount}/status', [HR\HRAccountController::class, 'status'])->name('accounts.status');
            Route::post('/hr-accounts/{hrAccount}/invitation', [HR\HRAccountController::class, 'resend'])->middleware('throttle:10,1')->name('accounts.invitation');
            Route::get('/local-mail', [HR\LocalMailController::class, 'index'])->name('mail.index');
            Route::get('/local-mail/{message}', [HR\LocalMailController::class, 'show'])->name('mail.show');
        });
    });
    foreach (['employees', 'branches', 'departments'] as $module) {
        Route::get('/'.$module, fn () => redirect()->route('hr.'.$module.'.index'))->middleware('hr')->name($module);
    }
    Route::get('/reports', [WorkspaceController::class, 'module'])->defaults('module', 'reports')->middleware('hr')->name('reports');
});

require __DIR__.'/phase5.php';
