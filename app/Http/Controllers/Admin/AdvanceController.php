<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Employee, EmployeeAdvance};
use App\Services\Admin\Context;
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvanceController extends Controller
{
    public function index(Request $request)
    {
        Context::authorize($request->user('admin'), 'hr.view');
        return view('admin.hr.advances', ['employees' => Employee::orderBy('name_ar')->get(),
            'advances' => EmployeeAdvance::with(['employee', 'repayments.payroll'])->when($request->employee_id, fn ($q, $id) => $q->where('employee_id', $id))->latest('date')->latest('id')->paginate(20)->withQueryString()]);
    }

    public function store(Request $request)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        $data = $request->validate(['employee_id' => 'required|integer|exists:erp_employees,id', 'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'amount' => 'required|numeric|gt:0', 'notes' => 'nullable|string|max:2000', 'request_id' => 'required|uuid']);
        $data['amount'] = D::of($data['amount']);
        DB::transaction(function () use ($data) {
            Context::lock();
            $employee = Employee::findOrFail($data['employee_id']);
            D::check($employee->active && $data['date'] >= $employee->hire_date, 'invalid_advance');
            EmployeeAdvance::firstOrCreate(['request_id' => $data['request_id']], $data);
        }, 3);
        return back()->with('success', __('erp.saved'));
    }

    public function destroy(Request $request, EmployeeAdvance $advance)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        DB::transaction(function () use ($advance) {
            Context::lock(); $row = EmployeeAdvance::findOrFail($advance->id);
            D::check(!$row->repayments()->exists(), 'advance_reserved'); $row->delete();
        }, 3);
        return back()->with('success', __('erp.deleted'));
    }
}
