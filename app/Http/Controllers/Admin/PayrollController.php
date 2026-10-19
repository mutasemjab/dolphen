<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Employee, Payroll};
use App\Services\Admin\Context;
use App\Services\Admin\PayrollService;
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        Context::authorize($request->user('admin'), 'hr.view');
        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = $request->input('month', now()->format('Y-m'));
        return view('admin.hr.payroll', ['month' => $month, 'employees' => Employee::where('active', true)->get(),
            'payrolls' => Payroll::with('employee')->where('month', $month)->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function store(Request $request)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        $data = $request->validate(['employee_id' => 'required|exists:erp_employees,id', 'month' => 'required|date_format:Y-m',
            'allowance' => 'required|numeric|min:0', 'deduction' => 'required|numeric|min:0', 'notes' => 'nullable|string|max:2000']);
        app(PayrollService::class)->save((int) $data['employee_id'], $data['month'], (string) $data['allowance'], (string) $data['deduction'], $data['notes'] ?? null);
        return redirect()->route('admin.payroll.index', ['month' => $data['month']])->with('success', __('erp.saved'));
    }

    public function paid(Request $request, Payroll $payroll)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        app(PayrollService::class)->paid($payroll);
        return back()->with('success', __('erp.saved'));
    }

    public function destroy(Request $request, Payroll $payroll)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        DB::transaction(function () use ($payroll) {
            Context::lock(); $row = Payroll::lockForUpdate()->findOrFail($payroll->id);
            D::check(!$row->paid_on, 'paid_payroll_locked'); $row->delete();
        }, 3);
        return back()->with('success', __('erp.deleted'));
    }

    public function show(Request $request, Payroll $payroll)
    {
        Context::authorize($request->user('admin'), 'hr.view');
        return view('admin.hr.payslip', ['payroll' => $payroll->load('employee')]);
    }
}
