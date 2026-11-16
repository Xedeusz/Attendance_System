<?php

use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\HR\LeaveAdministrationController;
use App\Http\Controllers\HR\SupervisorController;
use App\Http\Controllers\LeaveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::prefix('hr')->name('hr.')->middleware('hr')->group(function () {
        Route::middleware('password.confirm')->group(function () {
            Route::resource('supervisors', SupervisorController::class)->except('destroy');
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
        Route::get('leave/{leave}', [LeaveController::class, 'show'])->name('leave.show');
        Route::post('leave/{leave}/route', [LeaveAdministrationController::class, 'route'])->name('leave.route');
        Route::get('attendance/corrections', [AttendanceCorrectionController::class, 'index'])->name('corrections.index');
        Route::get('attendance/corrections/{correction}', [AttendanceCorrectionController::class, 'show'])->name('corrections.show');
        Route::post('attendance/corrections/{correction}/decision', [AttendanceCorrectionController::class, 'decide'])->name('corrections.decide');
    });
    Route::prefix('supervisor/leave')->name('supervisor.leave.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('pending', [LeaveController::class, 'index'])->name('pending');
        Route::get('history', [LeaveController::class, 'index'])->name('history');
        Route::get('notifications', [LeaveController::class, 'notices'])->name('notices');
        Route::post('notifications/{notice}/read', [LeaveController::class, 'readNotice'])->name('notices.read');
        Route::get('documents/{document}', [LeaveController::class, 'document'])->name('document');
        Route::get('{leave}', [LeaveController::class, 'show'])->name('show');
        Route::post('{leave}/decision', [LeaveController::class, 'decide'])->middleware('throttle:30,1')->name('decide');
    });
    Route::prefix('employee/leave')->name('employee.leave.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('create', [LeaveController::class, 'create'])->name('create');
        Route::post('/', [LeaveController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('documents/{document}', [LeaveController::class, 'document'])->name('document');
        Route::get('{leave}', [LeaveController::class, 'show'])->name('show');
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
