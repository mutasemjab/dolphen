<?php

namespace App\Services\Admin;

use App\Models\Admin\{Attendance, Employee, Payroll, WorkShift};
use App\Support\Admin\Decimal as D;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function schedule(WorkShift $shift, string $date): array
    {
        $start = Carbon::parse($date.' '.$shift->starts_at, 'Asia/Amman');
        $end = Carbon::parse($date.' '.$shift->ends_at, 'Asia/Amman');
        if ($end->lte($start)) { $end->addDay(); }
        return [$start, $end];
    }

    public function assertUnlocked(int $employeeId, string $date): void
    {
        D::check(!Payroll::where('employee_id', $employeeId)->where('month', substr($date, 0, 7))->whereNotNull('paid_on')->exists(), 'paid_attendance_locked');
    }

    public function location(array $coordinates): void
    {
        $policy = HrSettings::get();
        D::check($policy['latitude'] !== '' && $policy['longitude'] !== '', 'location_not_configured');
        D::check((float) $coordinates['accuracy'] <= min(100, (int) $policy['radius_meters']), 'location_inaccurate');
        $lat1 = deg2rad((float) $policy['latitude']); $lat2 = deg2rad((float) $coordinates['latitude']);
        $latDelta = $lat2 - $lat1;
        $lngDelta = deg2rad((float) $coordinates['longitude'] - (float) $policy['longitude']);
        $a = sin($latDelta / 2) ** 2 + cos($lat1) * cos($lat2) * sin($lngDelta / 2) ** 2;
        $distance = 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
        D::check($distance <= (int) $policy['radius_meters'], 'outside_workplace');
    }

    public function punch(Employee $employee, string $action, array $coordinates): Attendance
    {
        return DB::transaction(function () use ($employee, $action, $coordinates) {
            Context::lock(); $employee = Employee::with('shift')->findOrFail($employee->id);
            D::check($employee->active && (bool) $employee->username && (bool) $employee->password, 'inactive_record');
            $this->location($coordinates);
            $now = now('Asia/Amman')->startOfSecond();
            $open = Attendance::where('employee_id', $employee->id)->whereNotNull('check_in_at')->whereNull('check_out_at')->latest('date')->first();
            if ($action === 'out') {
                // A retry of an already completed checkout is harmless.
                if (!$open) {
                    $last = Attendance::where('employee_id', $employee->id)->whereNotNull('check_out_at')->latest('check_out_at')->first();
                    D::check($last && $last->check_out_at->diffInMinutes($now) <= 5, 'no_open_attendance');
                    return $last;
                }
                $this->assertUnlocked($employee->id, $open->date);
                D::check($now->gt($open->check_in_at) && $open->check_in_at->diffInHours($now) < 24, 'attendance_needs_review');
                $open->update(['check_out_at' => $now, 'check_out' => $now->format('H:i:s'),
                    'out_latitude' => $coordinates['latitude'], 'out_longitude' => $coordinates['longitude'], 'out_accuracy' => (int) ceil($coordinates['accuracy'])]);
                return $open;
            }
            if ($open) {
                D::check($open->check_in_at->diffInHours($now) < 24, 'attendance_needs_review');
                return $open;
            }
            D::check($employee->shift && $employee->shift->active, 'shift_required');
            $date = $now->toDateString();
            // The attendance day of an overnight shift is the day on which it starts.
            if ($employee->shift->ends_at <= $employee->shift->starts_at && $now->format('H:i:s') < $employee->shift->ends_at) { $date = $now->copy()->subDay()->toDateString(); }
            [$start, $end] = $this->schedule($employee->shift, $date);
            // A shift shortly after midnight may be checked into the previous evening.
            [$nextStart, $nextEnd] = $this->schedule($employee->shift, $now->copy()->addDay()->toDateString());
            if ($now->gte($nextStart->copy()->subHours(2)) && $now->lt($nextStart)) { $start = $nextStart; $end = $nextEnd; $date = $start->toDateString(); }
            // Work on a weekly rest day or official holiday is holiday overtime; employees may record it only when allowed.
            $policy = HrSettings::get();
            D::check($date >= $employee->hire_date && (!HrSettings::isDayOff($date, $policy) || $policy['employee_holiday_punch']), 'not_work_day');
            D::check($now->gte($start->copy()->subHours(2)) && $now->lt($end), 'outside_shift_window');
            $this->assertUnlocked($employee->id, $date);
            $approved = app(LeaveService::class)->approvedDays($employee->id, $date, $date)[$date] ?? [];
            D::check(!collect($approved)->contains('type', 'annual'), 'approved_leave_day');
            $existing = Attendance::where('employee_id', $employee->id)->where('date', $date)->first();
            D::check(!$existing, 'attendance_already_recorded');
            return Attendance::create(['employee_id' => $employee->id, 'date' => $date, 'status' => 'present',
                'scheduled_start' => $start, 'scheduled_end' => $end, 'check_in_at' => $now, 'check_in' => $now->format('H:i:s'), 'source' => 'employee',
                'in_latitude' => $coordinates['latitude'], 'in_longitude' => $coordinates['longitude'], 'in_accuracy' => (int) ceil($coordinates['accuracy'])]);
        }, 3);
    }

    public function manual(array $data, Attendance $record): array
    {
        if ($record->exists) { $this->assertUnlocked($record->employee_id, $record->date); }
        $this->assertUnlocked((int) $data['employee_id'], $data['date']);
        $employee = Employee::with('shift')->findOrFail($data['employee_id']);
        D::check((bool) $employee->shift, 'shift_required');
        D::check($data['date'] >= $employee->hire_date && $data['date'] <= now('Asia/Amman')->toDateString(), 'invalid_attendance_date');
        // On a rest day or official holiday only actual work is recorded; it is paid as holiday overtime.
        D::check(!HrSettings::isDayOff($data['date']) || $data['status'] === 'present', 'holiday_present_only');
        $approved = app(LeaveService::class)->approvedDays($employee->id, $data['date'], $data['date'])[$data['date']] ?? [];
        D::check(!collect($approved)->contains('type', 'annual'), 'approved_leave_day');
        [$start, $end] = $record->exists && $record->scheduled_start && $record->employee_id == $employee->id && $record->date === $data['date']
            ? [$record->scheduled_start, $record->scheduled_end] : $this->schedule($employee->shift, $data['date']);
        $in = $out = null;
        if ($data['status'] === 'present') {
            D::check(!empty($data['check_in']), 'attendance_time_required');
            $in = Carbon::parse($data['date'].' '.$data['check_in'], 'Asia/Amman');
            if ($end->toDateString() !== $start->toDateString() && $in->lt($start->copy()->subHours(2))) { $in->addDay(); }
            D::check($in->gte($start->copy()->subHours(2)) && $in->lt($end), 'outside_shift_window');
            if (!empty($data['check_out'])) {
                $out = Carbon::parse($data['date'].' '.$data['check_out'], 'Asia/Amman');
                if ($out->lte($in) && $end->toDateString() !== $start->toDateString()) { $out->addDay(); }
                D::check($out->gt($in) && $in->diffInHours($out) < 24, 'invalid_attendance_time');
            }
            D::check($in->lte(now('Asia/Amman')) && (!$out || $out->lte(now('Asia/Amman'))), 'invalid_attendance_time');
        } else {
            D::check(empty($data['check_in']) && empty($data['check_out']), 'status_time_conflict');
        }
        return array_replace($data, ['scheduled_start' => $start, 'scheduled_end' => $end, 'check_in_at' => $in, 'check_out_at' => $out,
            'check_in' => $in?->format('H:i:s'), 'check_out' => $out?->format('H:i:s'), 'source' => 'admin',
            'in_latitude' => null, 'in_longitude' => null, 'out_latitude' => null, 'out_longitude' => null, 'in_accuracy' => null, 'out_accuracy' => null]);
    }
}
