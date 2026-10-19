<?php

use App\Http\Controllers\Admin\LoginController;
use App\Http\Middleware\AdminLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(AdminLocale::class)->prefix('admin')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [LoginController::class, 'show_login_view'])->name('admin.showlogin');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1')->name('admin.login');
    });
    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('admin.logout');
        Route::get('admin/edit/{id}', [LoginController::class, 'editlogin'])->name('admin.login.edit');
        Route::post('admin/update/{id}', [LoginController::class, 'updatelogin'])->name('admin.login.update');
        Route::get('employee', fn () => redirect()->route('admin.access'))->name('admin.employee.index');
        Route::get('role', fn () => redirect()->route('admin.access'))->name('admin.role.index');
    });
});

require __DIR__.'/admin_factory.php';