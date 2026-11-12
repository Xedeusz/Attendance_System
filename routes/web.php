<?php

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::view('/profile', 'workspace.profile')->name('profile');
    Route::view('/settings', 'workspace.settings')->name('settings');
    foreach (array_keys(config('workspace.modules')) as $module) {
        Route::get('/'.$module, [WorkspaceController::class, 'module'])->defaults('module', $module)->name($module);
    }
});
