<?php

namespace App\Services\Admin;

use App\Models\Admin\{Attendance, Employee, LeaveBalance, LeaveRequest};
use App\Support\Admin\Decimal as D;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function balance(Employee $employee, int $year): array
    {
        $default = $year >= now('Asia/Amman')->year && $year >= (int) substr($employee->hire_date, 0, 4) ? $employee->annual_leave_days : '0';
        $allowance = LeaveBalance::where('employee_id', $employee->id)->where('year', $year)->value('allowance') ?? $default;
        $requests = LeaveRequest::where('employee_id', $employee->id)->where('year', $year)->whereIn('status', ['pending', 'approved'])->get();
        $used = D::sum($requests->where('status', 'approved')->pluck('charged_days'));
        $reserved = D::sum($requests->where('status', 'pending')->pluck('charged_days'));
        return ['year' => $year, 'allowance' => D::of($allowance), 'used' => $used, 'reserved' => $reserved,
            'available' => D::sub(D::sub($allowance, $used), $reserved)];
    }

    // Called in the employee save transaction. Old calendar-year allocations stay intact.
    public function setAllowance(Employee $employee): void
    {
        $year = now('Asia/Amman')->year;
        $years = LeaveBalance::where('employee_id', $employee->id)->where('year', '>=', $year)->pluck('year')->push($year)->unique();
        foreach ($years as $balanceYear) {
            $balance = $this->balance($employee, (int) $balanceYear);
            D::check(D::cmp($employee->annual_leave_days, D::add($balance['used'], $balance['reserved'])) >= 0, 'leave_allowance_too_low');
            LeaveBalance::updateOrCreate(['employee_id' => $employee->id, 'year' => $balanceYear], ['allowance' => $employee->annual_leave_days]);
        }
    }

    public function submit(Employee $employee, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $data) {
            Context::lock(); $employee = Employee::with('shift')->findOrFail($employee->id);
            D::check($employee->active && $employee->shift?->active, 'shift_required');
            if ($existing = LeaveRequest::where('request_id', $data['request_id'])->first()) {
                D::check($existing->employee_id === $employee->id, 'duplicate_record'); return $existing;
            }
            $today = now('Asia/Amman')->startOfDay();
            $first = Carbon::parse($data['start_date'], 'Asia/Amman'); $last = Carbon::parse($data['end_date'], 'Asia/Amman');
            D::check($first->gte($today) && $first->toDateString() >= $employee->hire_date && $last->gte($first), 'invalid_leave_dates');
            D::check($first->year === $last->year && $first->year <= $today->year + 1, 'leave_same_year');
            D::check($data['type'] !== 'departure' || $first->eq($last), 'departure_single_day');
            $days = []; $attendanceService = app(AttendanceService::class); $workDays = HrSettings::get()['work_days'];
            for ($date = $first->copy(); $date->lte($last); $date->addDay()) {
                if (!in_array($date->dayOfWeek, $workDays)) { continue; }
                $day = $date->toDateString(); $attendanceService->assertUnlocked($employee->id, $day);
                $attendance = Attendance::where('employee_id', $employee->id)->where('date', $day)->first();
                [$start, $end] = $attendance?->scheduled_start ? [$attendance->scheduled_start, $attendance->scheduled_end] : $attendanceService->schedule($employee->shift, $day);
                $from = $start->copy(); $to = $end->copy(); $fraction = '1.000000';
                if ($data['type'] === 'departure') {
                    $from = Carbon::parse($day.' '.$data['from_time'], 'Asia/Amman');
                    if ($end->toDateString() !== $day && $from->lt($start)) { $from->addDay(); }
                    $to = Carbon::parse($day.' '.$data['to_time'], 'Asia/Amman');
                    if ($end->toDateString() !== $day && $to->lte($from)) { $to->addDay(); }
                    D::check($from->gte($start) && $to->lte($end) && $to->gt($from) && $from->diffInMinutes($to) < $start->diffInMinutes($end), 'departure_inside_shift');
                    $fraction = bcadd(bcdiv((string) $from->diffInMinutes($to), (string) $start->diffInMinutes($end), 12), '0.0000005', 6);
                } else {
                    D::check(!$attendance || (!$attendance->check_in && !$attendance->check_out && $attendance->status === 'absent'), 'leave_attendance_conflict');
                }
                $days[$day] = ['start' => $from->toDateTimeString(), 'end' => $to->toDateTimeString(), 'shift_start' => $start->toDateTimeString(), 'shift_end' => $end->toDateTimeString(), 'fraction' => $fraction];
            }
            D::check(count($days) > 0, 'leave_no_work_days');
            $others = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
                ->where('start_date', '<=', $last->toDateString())->where('end_date', '>=', $first->toDateString())->get();
            foreach ($others as $other) {
                foreach ($days as $date => $day) {
                    $previous = $other->days[$date] ?? null;
                    D::check(!$previous || $day['start'] >= $previous['end'] || $day['end'] <= $previous['start'], 'leave_overlap');
                }
            }
            $charged = D::sum(array_column($days, 'fraction'));
            LeaveBalance::firstOrCreate(['employee_id' => $employee->id, 'year' => $first->year], ['allowance' => $employee->annual_leave_days]);
            D::check(D::cmp($this->balance($employee, $first->year)['available'], $charged) >= 0, 'insufficient_leave_balance');
            return LeaveRequest::create(['employee_id' => $employee->id, 'request_id' => $data['request_id'], 'type' => $data['type'],
                'start_date' => $first->toDateString(), 'end_date' => $last->toDateString(), 'year' => $first->year,
                'from_time' => $data['type'] === 'departure' ? $data['from_time'] : null, 'to_time' => $data['type'] === 'departure' ? $data['to_time'] : null,
                'days' => $days, 'charged_days' => $charged, 'reason' => $data['reason'], 'status' => 'pending']);
        }, 3);
    }

    public function review(LeaveRequest $request, string $status, int $adminId, ?string $note): void
    {
        DB::transaction(function () use ($request, $status, $adminId, $note) {
            Context::lock(); $row = LeaveRequest::with('employee')->findOrFail($request->id);
            if ($row->status === $status) { return; }
            D::check($row->status === 'pending' || ($row->status === 'approved' && $status === 'cancelled'), 'leave_already_reviewed');
            if ($status === 'approved' || $row->status === 'approved') {
                foreach ($row->days as $date => $day) {
                    app(AttendanceService::class)->assertUnlocked($row->employee_id, $date);
                    if ($status === 'approved' && $row->type === 'annual') {
                        $attendance = Attendance::where('employee_id', $row->employee_id)->where('date', $date)->first();
                        D::check(!$attendance || (!$attendance->check_in && !$attendance->check_out && $attendance->status === 'absent'), 'leave_attendance_conflict');
                    }
                }
            }
            if ($status === 'approved') {
                D::check($row->employee->active, 'inactive_record');
                D::check(D::cmp($this->balance($row->employee, $row->year)['available']) >= 0, 'insufficient_leave_balance');
            }
            $row->update(['status' => $status, 'reviewed_by' => $adminId, 'reviewed_at' => now('Asia/Amman'), 'review_note' => $note]);
        }, 3);
    }

    public function cancel(Employee $employee, int $id): void
    {
        DB::transaction(function () use ($employee, $id) {
            Context::lock(); $row = LeaveRequest::where('employee_id', $employee->id)->findOrFail($id);
            if ($row->status === 'cancelled') { return; }
            D::check($row->status === 'pending', 'leave_cancel_pending_only');
            $row->update(['status' => 'cancelled']);
        }, 3);
    }

    /** Approved intervals by work date, with their original shift and duration. */
    public function approvedDays(int $employeeId, string $first, string $last): array
    {
        $days = [];
        $requests = LeaveRequest::where('employee_id', $employeeId)->where('status', 'approved')->where('start_date', '<=', $last)->where('end_date', '>=', $first)->get();
        foreach ($requests as $request) {
            foreach ($request->days as $date => $day) {
                if ($date >= $first && $date <= $last) { $days[$date][] = $day + ['type' => $request->type, 'id' => $request->id]; }
            }
        }
        return $days;
    }

    public function coveredMinutes(array $intervals, Carbon $start, Carbon $end): int
    {
        if ($end->lte($start)) { return 0; }
        $total = 0;
        foreach ($intervals as $interval) {
            $from = Carbon::parse($interval['start'], 'Asia/Amman')->max($start);
            $to = Carbon::parse($interval['end'], 'Asia/Amman')->min($end);
            if ($to->gt($from)) { $total += $from->diffInMinutes($to); }
        }
        return min($start->diffInMinutes($end), $total);
    }
}
