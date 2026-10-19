<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Attendance, EmployeeAdvance, Payroll};
use App\Services\Admin\{AttendanceService, HrSettings, LeaveService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EmployeePortalController extends Controller
{
    public function loginForm() { return view('admin.employee.login'); }

    public function login(Request $request)
    {
        $data = $request->validate(['username' => 'required|string|max:100', 'password' => 'required|string|max:200']);
        if (!Auth::guard('employee')->attempt($data + ['active' => true])) {
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }
        $request->session()->regenerate();
        $request->session()->put('employee_auth_hash', hash('sha256', Auth::guard('employee')->user()->password));
        return redirect()->route('employee.dashboard');
    }

    public function dashboard(Request $request)
    {
        $employee = $request->user('employee')->load('shift');
        return view('admin.employee.dashboard', ['employee' => $employee, 'policy' => HrSettings::get(),
            'leaveBalance' => app(LeaveService::class)->balance($employee, now()->year),
            'open' => Attendance::where('employee_id', $employee->id)->whereNotNull('check_in_at')->whereNull('check_out_at')->latest('date')->first(),
            'attendance' => Attendance::where('employee_id', $employee->id)->latest('date')->limit(31)->get(),
            'payrolls' => Payroll::where('employee_id', $employee->id)->latest('month')->limit(12)->get(),
            'advances' => EmployeeAdvance::with('repayments.payroll')->where('employee_id', $employee->id)->latest('date')->limit(100)->get()]);
    }

    public function punch(Request $request, AttendanceService $service)
    {
        $data = $request->validate(['action' => 'required|in:in,out', 'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180', 'accuracy' => 'required|numeric|gt:0|max:10000']);
        $service->punch($request->user('employee'), $data['action'], $data);
        return redirect()->route('employee.dashboard')->with('success', __('erp.attendance_saved'));
    }

    public function payslip(Request $request, int $payroll)
    {
        $payroll = Payroll::where('employee_id', $request->user('employee')->id)->findOrFail($payroll);
        return view('admin.employee.payslip', compact('payroll'));
    }

    public function logout(Request $request)
    {
        Auth::guard('employee')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('employee.login');
    }
}
