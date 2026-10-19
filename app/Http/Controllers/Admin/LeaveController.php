<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Employee, LeaveRequest};
use App\Services\Admin\{Context, LeaveService};
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request, LeaveService $service)
    {
        Context::authorize($request->user('admin'), 'hr.view');
        $request->validate(['year'=>'nullable|integer|min:2000|max:2100','employee_id'=>'nullable|integer|exists:erp_employees,id','status'=>'nullable|in:pending,approved,rejected,cancelled']);
        $year = (int) $request->input('year', now()->year);
        $employees = Employee::orderBy('name_ar')->get();
        $selected = $employees->firstWhere('id', (int) $request->employee_id);
        return view('admin.hr.leaves', ['employees'=>$employees,'year'=>$year,'balance'=>$selected ? $service->balance($selected,$year) : null,
            'requests'=>LeaveRequest::with(['employee','reviewer'])->where('year',$year)
                ->when($request->employee_id,fn($q,$id)=>$q->where('employee_id',$id))->when($request->status,fn($q,$status)=>$q->where('status',$status))
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest('id')->paginate(20)->withQueryString()]);
    }

    public function review(Request $request, LeaveRequest $leave, LeaveService $service)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        $data = $request->validate(['status'=>'required|in:approved,rejected,cancelled','review_note'=>'nullable|string|max:2000']);
        $service->review($leave,$data['status'],$request->user('admin')->id,$data['review_note'] ?? null);
        return back()->with('success',__('erp.saved'));
    }

    public function employeeIndex(Request $request, LeaveService $service)
    {
        $request->validate(['year'=>'nullable|integer|min:2000|max:2100']);
        $year = (int) $request->input('year',now()->year); $employee = $request->user('employee');
        return view('admin.employee.leaves',['year'=>$year,'balance'=>$service->balance($employee,$year),
            'requests'=>LeaveRequest::where('employee_id',$employee->id)->where('year',$year)->latest('id')->paginate(15)->withQueryString()]);
    }

    public function store(Request $request, LeaveService $service)
    {
        $data = $request->validate(['type'=>'required|in:annual,departure','start_date'=>'required|date_format:Y-m-d',
            'end_date'=>'required|date_format:Y-m-d','from_time'=>'nullable|required_if:type,departure|date_format:H:i',
            'to_time'=>'nullable|required_if:type,departure|date_format:H:i','reason'=>'required|string|max:2000','request_id'=>'required|uuid']);
        $leave = $service->submit($request->user('employee'),$data);
        return redirect()->route('employee.leaves',['year'=>$leave->year])->with('success',__('erp.leave_submitted'));
    }

    public function cancel(Request $request, int $leave, LeaveService $service)
    {
        $service->cancel($request->user('employee'),$leave);
        return back()->with('success',__('erp.saved'));
    }
}
