<?php

use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\HR\LeaveAdministrationController;
use App\Http\Controllers\HR\SupervisorController;
use App\Http\Controllers\LeaveCalendarController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveVerificationController;
use App\Http\Controllers\SupervisorSignatureController;
use Illuminate\Support\Facades\Route;

Route::prefix('verify/leave')->name('leave.verify.')->group(function () {
    Route::get('/', [LeaveVerificationController::class, 'landing'])->name('landing');
    Route::post('/', [LeaveVerificationController::class, 'capture'])->middleware('throttle:20,1,leave-scan:')->name('capture');
    Route::middleware(['auth', 'auth.session', 'throttle:60,1,leave-verify:'])->group(function () {
        Route::get('scan', [LeaveVerificationController::class, 'scan'])->name('scan');
        Route::get('search', [LeaveVerificationController::class, 'search'])->middleware('hr')->name('search');
        Route::get('documents/{document}', [LeaveVerificationController::class, 'show'])->whereNumber('document')->name('show');
        Route::get('documents/{document}/original', [LeaveVerificationController::class, 'original'])->whereNumber('document')->name('original');
        Route::post('documents/{document}/revoke', [LeaveVerificationController::class, 'revoke'])->middleware('hr')->name('revoke');
        Route::post('documents/{document}/replace', [LeaveVerificationController::class, 'replace'])->name('replace');
    });
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::prefix('hr')->name('hr.')->middleware('hr')->group(function () {
        Route::middleware('password.confirm')->group(function () {
            Route::resource('supervisors', SupervisorController::class)->except('destroy');
            Route::post('supervisors/{supervisor}/department', [SupervisorController::class, 'department'])->name('supervisors.department');
            Route::post('supervisors/{supervisor}/assignments', [SupervisorController::class, 'assign'])->name('supervisors.assign');
            Route::post('supervisors/{supervisor}/assignments/{assignment}/revoke', [SupervisorController::class, 'revoke'])->name('supervisors.revoke');
            Route::post('supervisors/{supervisor}/status', [SupervisorController::class, 'status'])->name('supervisors.status');
            Route::post('supervisors/{supervisor}/invitation', [SupervisorController::class, 'resend'])->middleware('throttle:10,1')->name('supervisors.resend');
        });
        Route::get('leave', [LeaveAdministrationController::class, 'index'])->name('leave.index');
        Route::get('leave/types', [LeaveAdministrationController::class, 'types'])->name('leave.types');
        Route::post('leave/types', [LeaveAdministrationController::class, 'saveType'])->middleware('password.confirm')->name('leave.types.store');
        Route::put('leave/types/{type}', [LeaveAdministrationController::class, 'saveType'])->middleware('password.confirm')->name('leave.types.update');
        Route::get('leave/balances', [LeaveAdministrationController::class, 'balances'])->name('leave.balances');
        Route::post('leave/balances', [LeaveAdministrationController::class, 'adjust'])->middleware('password.confirm')->name('leave.balances.adjust');
        Route::get('leave/documents/{document}', [LeaveController::class, 'document'])->name('leave.document');
        Route::get('leave/calendar', [LeaveCalendarController::class, 'index'])->name('leave.calendar.index');
        Route::get('leave/calendar/events', [LeaveCalendarController::class, 'events'])->middleware('throttle:90,1,leave-calendar:')->name('leave.calendar.events');
        Route::get('leave/calendar/events/{event}', [LeaveCalendarController::class, 'details'])->whereNumber('event')->middleware('throttle:90,1,leave-calendar:')->name('leave.calendar.details');
        Route::get('leave/{leave}', [LeaveController::class, 'show'])->name('leave.show');
        Route::get('leave/{leave}/form', [LeaveController::class, 'preview'])->name('leave.form');
        Route::get('leave/{leave}/approved-form', [LeaveController::class, 'approvedForm'])->middleware('throttle:60,1,leave-approved-form:')->name('leave.approved-form');
        Route::post('leave/{leave}/route', [LeaveAdministrationController::class, 'route'])->name('leave.route');
        Route::get('attendance/corrections', [AttendanceCorrectionController::class, 'index'])->name('corrections.index');
        Route::get('attendance/corrections/{correction}', [AttendanceCorrectionController::class, 'show'])->name('corrections.show');
        Route::post('attendance/corrections/{correction}/decision', [AttendanceCorrectionController::class, 'decide'])->name('corrections.decide');
    });
    Route::prefix('supervisor/account/electronic-signature')->name('supervisor.signature.')->group(function () {
        Route::get('/', [SupervisorSignatureController::class, 'show'])->name('show');
        Route::get('image', [SupervisorSignatureController::class, 'image'])->middleware('throttle:60,1,leave-signature-read:')->name('image');
        Route::post('/', [SupervisorSignatureController::class, 'store'])->middleware('throttle:10,1,leave-signature-write:')->name('store');
    });
    Route::prefix('supervisor/leave')->name('supervisor.leave.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('employees/{employee}/profile', [EmployeeProfileController::class, 'show'])->name('employees.profile');
        Route::get('pending', [LeaveController::class, 'index'])->name('pending');
        Route::get('history', [LeaveController::class, 'index'])->name('history');
        Route::get('notifications', [LeaveController::class, 'notices'])->name('notices');
        Route::post('notifications/{notice}/read', [LeaveController::class, 'readNotice'])->name('notices.read');
        Route::get('documents/{document}', [LeaveController::class, 'document'])->name('document');
        Route::get('calendar', [LeaveCalendarController::class, 'index'])->name('calendar.index');
        Route::get('calendar/events', [LeaveCalendarController::class, 'events'])->middleware('throttle:90,1,leave-calendar:')->name('calendar.events');
        Route::get('calendar/events/{event}', [LeaveCalendarController::class, 'details'])->whereNumber('event')->middleware('throttle:90,1,leave-calendar:')->name('calendar.details');
        Route::get('{leave}', [LeaveController::class, 'show'])->name('show');
        Route::get('{leave}/form', [LeaveController::class, 'preview'])->name('form');
        Route::get('{leave}/approved-form', [LeaveController::class, 'approvedForm'])->middleware('throttle:60,1,leave-approved-form:')->name('approved-form');
        Route::post('{leave}/decision', [LeaveController::class, 'decide'])->middleware('throttle:30,1')->name('decide');
    });
    Route::prefix('employee/leave')->name('employee.leave.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('create', [LeaveController::class, 'create'])->name('create');
        Route::post('/', [LeaveController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('documents/{document}', [LeaveController::class, 'document'])->name('document');
        Route::get('calendar', [LeaveCalendarController::class, 'index'])->name('calendar.index');
        Route::get('calendar/events', [LeaveCalendarController::class, 'events'])->middleware('throttle:90,1,leave-calendar:')->name('calendar.events');
        Route::get('calendar/events/{event}', [LeaveCalendarController::class, 'details'])->whereNumber('event')->middleware('throttle:90,1,leave-calendar:')->name('calendar.details');
        Route::get('{leave}', [LeaveController::class, 'show'])->name('show');
        Route::get('{leave}/form', [LeaveController::class, 'preview'])->name('form');
        Route::get('{leave}/approved-form', [LeaveController::class, 'approvedForm'])->middleware('throttle:60,1,leave-approved-form:')->name('approved-form');
        Route::post('{leave}/cancel', [LeaveController::class, 'cancel'])->name('cancel');
    });
    Route::prefix('employee/attendance/corrections')->name('employee.corrections.')->group(function () {
        Route::get('/', [AttendanceCorrectionController::class, 'index'])->name('index');
        Route::get('create', [AttendanceCorrectionController::class, 'create'])->name('create');
        Route::post('/', [AttendanceCorrectionController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('{correction}', [AttendanceCorrectionController::class, 'show'])->name('show');
        Route::post('{correction}/cancel', [AttendanceCorrectionController::class, 'cancel'])->name('cancel');
    });
    Route::post('notifications/{notice}/read', [LeaveController::class, 'readNotice'])->name('notifications.read');
});
