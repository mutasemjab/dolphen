<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\{Context, HrSettings};
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrSettingsController extends Controller
{
    public function index(Request $request)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        return view('admin.hr.settings', ['settings' => HrSettings::get()]);
    }

    public function save(Request $request)
    {
        Context::authorize($request->user('admin'), 'hr.manage');
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:5000', 'work_days' => 'required|array|min:1|max:7',
            'work_days.*' => 'required|integer|between:0,6|distinct', 'grace_minutes' => 'required|integer|min:0|max:120',
            'overtime_hour_rate' => 'required|numeric|min:0', 'late_hour_rate' => 'required|numeric|min:0',
            'holiday_overtime_hour_rate' => 'nullable|numeric|min:0', 'foreign_overtime_hour_rate' => 'nullable|numeric|min:0',
            'foreign_holiday_overtime_hour_rate' => 'nullable|numeric|min:0', 'employee_holiday_punch' => 'nullable|boolean',
            'holidays' => 'nullable|array|max:200', 'holidays.*.date' => 'required|date_format:Y-m-d|distinct', 'holidays.*.name' => 'nullable|string|max:200',
        ]);
        $data['work_days'] = array_map('intval', $data['work_days']); sort($data['work_days']);
        foreach (['overtime_hour_rate', 'late_hour_rate'] as $field) { $data[$field] = D::of($data[$field]); }
        foreach (['holiday_overtime_hour_rate', 'foreign_overtime_hour_rate', 'foreign_holiday_overtime_hour_rate'] as $field) {
            $data[$field] = isset($data[$field]) ? D::of((string) $data[$field]) : '';
        }
        $data['employee_holiday_punch'] = (bool) ($data['employee_holiday_punch'] ?? false);
        $data['holidays'] = collect($data['holidays'] ?? [])->map(fn ($h) => ['date' => $h['date'], 'name' => (string) ($h['name'] ?? '')])
            ->sortBy('date')->values()->all();
        DB::transaction(function () use ($data) { Context::lock(); HrSettings::save($data); }, 3);
        return back()->with('success', __('erp.saved'));
    }
}
