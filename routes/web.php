<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AttendanceEvidenceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeAttendanceController;
use App\Http\Controllers\EmployeeWorkCalendarController;
use App\Http\Controllers\HR;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\AttendanceReviewController;
use App\Http\Controllers\NotificationDropdownController;
use App\Http\Controllers\PrivacyNoticeController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\SelfServiceController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceSyncController;
use App\Http\Middleware\RequirePrivacyAcknowledgement;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/activate-account', [ActivationController::class, 'show'])->middleware('throttle:30,1')->name('activation.show');
    Route::post('/activate-account', [ActivationController::class, 'store'])->middleware('throttle:10,1')->name('activation.store');
});
Route::middleware(['auth', 'auth.session'])->prefix('privacy')->name('privacy.')->group(function () {
    Route::get('/', [PrivacyNoticeController::class, 'index'])->name('index');
    Route::get('/versions/{notice}', [PrivacyNoticeController::class, 'show'])->whereNumber('notice')->name('show');
    Route::post('/acknowledgement', [PrivacyNoticeController::class, 'acknowledge'])->middleware('throttle:12,1,privacy-ack:')->name('acknowledge');
});
Route::middleware(['auth', 'auth.session', RequirePrivacyAcknowledgement::class])->group(function () {
    Route::get('/realtime/state', WorkspaceSyncController::class)->middleware('throttle:120,1,workspace-sync:')->name('realtime.state');
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->workspaceRoute()))->name('dashboard');
    Route::get('/profile/photo/employees/{employee}/{version}', [ProfilePhotoController::class, 'employee'])->middleware('throttle:120,1,profile-photo-read:')->name('employee.profile.photo');
    Route::get('/profile/photo/{version}', [ProfilePhotoController::class, 'show'])->middleware('throttle:120,1,profile-photo-read:')->name('profile.photo');
    Route::post('/employee/profile/photo', [ProfilePhotoController::class, 'store'])->middleware('throttle:10,1,profile-photo-write:')->name('profile.photo.store');
    Route::delete('/employee/profile/photo', [ProfilePhotoController::class, 'destroy'])->middleware('throttle:10,1,profile-photo-write:')->name('profile.photo.destroy');
    Route::get('/profile', [SelfServiceController::class, 'profile'])->name('profile');
    Route::patch('/profile', [SelfServiceController::class, 'update'])->name('profile.update');
    Route::get('/settings', [SelfServiceController::class, 'settings'])->name('settings');
    Route::get('/employee/dashboard', [DashboardController::class, 'employee'])->name('employee.dashboard');
    Route::get('/employee/profile', [SelfServiceController::class, 'profile'])->name('employee.profile');
    Route::patch('/employee/profile', [SelfServiceController::class, 'update'])->name('employee.profile.update');
    Route::prefix('notifications/dropdown')->name('notifications.dropdown.')->group(function () {
        Route::get('/', [NotificationDropdownController::class, 'index'])->middleware('throttle:60,1,notice-list:')->name('index');
        Route::post('read-all', [NotificationDropdownController::class, 'readAll'])->middleware('throttle:30,1,notice-write:')->name('read-all');
        Route::post('{notice}/read', [NotificationDropdownController::class, 'read'])->whereUuid('notice')->middleware('throttle:30,1,notice-write:')->name('read');
    });
    Route::get('/notifications', [WorkspaceController::class, 'notifications'])->name('notifications');
    Route::get('/leave', fn () => redirect()->route(auth()->user()->isHr() ? 'hr.leave.index' : (auth()->user()->isSupervisor() ? 'supervisor.leave.index' : 'employee.leave.index')))->name('leave');
    Route::get('/attendance', fn () => redirect()->route(auth()->user()->isHr() ? 'hr.attendance.index' : 'employee.attendance'))->name('attendance');
    Route::get('/attendance-history', fn () => redirect()->route('employee.attendance.history'))->name('attendance.history');
    Route::get('/employee/attendance', [EmployeeAttendanceController::class, 'index'])->name('employee.attendance');
    Route::get('/employee/attendance/calendar', EmployeeWorkCalendarController::class)->name('employee.work-calendar');
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
        Route::middleware(['can:manage-privacy-notices', 'password.confirm'])->prefix('privacy-notices')->name('privacy.')->group(function () {
            Route::get('/', [HR\PrivacyNoticeController::class, 'index'])->name('index');
            Route::post('/', [HR\PrivacyNoticeController::class, 'store'])->name('store');
            Route::get('/{notice}', [HR\PrivacyNoticeController::class, 'show'])->whereNumber('notice')->name('show');
            Route::patch('/{notice}', [HR\PrivacyNoticeController::class, 'update'])->whereNumber('notice')->name('update');
            Route::post('/{notice}/publish', [HR\PrivacyNoticeController::class, 'publish'])->whereNumber('notice')->name('publish');
            Route::post('/{notice}/archive', [HR\PrivacyNoticeController::class, 'archive'])->whereNumber('notice')->name('archive');
        });
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::prefix('attendance')->group(function () {
            Route::resource('schedules', HR\WorkScheduleController::class)->except(['show', 'destroy']);
            Route::patch('schedules/{schedule}/deactivate', [HR\WorkScheduleController::class, 'deactivate'])->name('schedules.deactivate');
            Route::post('schedule-exceptions', [HR\WorkScheduleController::class, 'storeException'])->name('schedule-exceptions.store');
            Route::patch('schedule-exceptions/{exception}/cancel', [HR\WorkScheduleController::class, 'cancelException'])->name('schedule-exceptions.cancel');
            Route::resource('holidays', HR\AttendanceHolidayController::class)->except('destroy');
            Route::get('holiday-import', [HR\AttendanceHolidayController::class, 'importForm'])->name('holiday-import.index');
            Route::get('holiday-import/preview', [HR\AttendanceHolidayController::class, 'reviewImport'])->name('holiday-import.review');
            Route::post('holiday-import/preview', [HR\AttendanceHolidayController::class, 'importPreview'])->middleware('throttle:20,1')->name('holiday-import.preview');
            Route::post('holiday-import', [HR\AttendanceHolidayController::class, 'importConfirm'])->middleware('throttle:20,1')->name('holiday-import.store');
        });
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
