<?php

use App\Http\Controllers\Admin\{FactoryDashboardController, MasterController, InvoiceController, StockController, PayrollController, AccessController, HrSettingsController, AdvanceController, EmployeePortalController, LeaveController};
use App\Http\Middleware\AdminLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(AdminLocale::class)->prefix('admin')->name('admin.')->group(function () {
    Route::post('language/{locale}', function (Illuminate\Http\Request $request, string $locale) {
        abort_unless(in_array($locale, ['ar', 'en']), 404);
        $request->session()->put('erp_locale', $locale);
        return back();
    })->name('language');
    Route::middleware('auth:admin')->group(function () {
        Route::get('/', FactoryDashboardController::class)->name('dashboard');
        Route::get('masters/{resource}', [MasterController::class, 'index'])->name('masters.index');
        Route::get('masters/{resource}/create', [MasterController::class, 'form'])->name('masters.create');
        Route::get('masters/{resource}/{record}/edit', [MasterController::class, 'form'])->whereNumber('record')->name('masters.edit');
        Route::post('masters/{resource}', [MasterController::class, 'save'])->name('masters.store');
        Route::put('masters/{resource}/{record}', [MasterController::class, 'save'])->whereNumber('record')->name('masters.update');
        Route::get('invoices/{type}', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{type}/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices/{type}', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoice/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoice/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('invoice/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('stock', [StockController::class, 'index'])->name('stock');
        Route::post('stock', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{payroll}/paid', [PayrollController::class, 'paid'])->name('payroll.paid');
        Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
        Route::get('hr/settings', [HrSettingsController::class, 'index'])->name('hr.settings');
        Route::get('hr/leaves', [LeaveController::class, 'index'])->name('hr.leaves');
        Route::post('hr/leaves/{leave}/review', [LeaveController::class, 'review'])->name('hr.leaves.review');
        Route::post('hr/settings', [HrSettingsController::class, 'save'])->name('hr.settings.save');
        Route::get('hr/advances', [AdvanceController::class, 'index'])->name('hr.advances');
        Route::post('hr/advances', [AdvanceController::class, 'store'])->name('hr.advances.store');
        Route::delete('hr/advances/{advance}', [AdvanceController::class, 'destroy'])->name('hr.advances.destroy');
        Route::get('access', [AccessController::class, 'index'])->name('access');
        Route::post('access/users', [AccessController::class, 'save'])->name('access.store');
        Route::put('access/users/{user}', [AccessController::class, 'save'])->whereNumber('user')->name('access.update');
    });
});

Route::middleware(AdminLocale::class)->prefix('admin/employee-portal')->name('employee.')->group(function () {
    Route::get('login', [EmployeePortalController::class, 'loginForm'])->name('login');
    Route::post('login', [EmployeePortalController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::middleware(\App\Http\Middleware\EmployeeSession::class)->group(function () {
        Route::get('/', [EmployeePortalController::class, 'dashboard'])->name('dashboard');
        Route::get('leaves', [LeaveController::class, 'employeeIndex'])->name('leaves');
        Route::post('leaves', [LeaveController::class, 'store'])->middleware('throttle:12,1')->name('leaves.store');
        Route::post('leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->name('leaves.cancel');
        Route::post('attendance', [EmployeePortalController::class, 'punch'])->middleware('throttle:12,1')->name('attendance');
        Route::get('payslip/{payroll}', [EmployeePortalController::class, 'payslip'])->whereNumber('payroll')->name('payslip');
        Route::post('logout', [EmployeePortalController::class, 'logout'])->name('logout');
    });
});
