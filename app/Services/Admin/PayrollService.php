<?php

namespace App\Services\Admin;

use App\Models\Admin\{Attendance, Employee, EmployeeAdvance, Payroll, PayrollAdvance, LeaveRequest};
use App\Support\Admin\Decimal as D;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    private function money(string $amount): string
    {
        $places = max(0, min(6, (int) Context::setting('currency_decimals', '3')));
        $half = $places === 0 ? '0.5' : '0.'.str_repeat('0', $places).'5';
        return bcadd(bcadd($amount, $half, 7), '0', $places);
    }

    /** Pure calculation: persisting the snapshot and reserving advances happen together below. */
    public function calculate(Employee $employee, string $month, string $allowance = '0', string $deduction = '0', ?Payroll $existing = null): array
    {
        $employee->load('shift');
        D::check((bool) $employee->shift, 'shift_required');
        $policy = HrSettings::get(); $attendanceService = app(AttendanceService::class);
        $first = Carbon::createFromFormat('!Y-m', $month, 'Asia/Amman'); $last = $first->copy()->endOfMonth(); $now = now('Asia/Amman');
        D::check($first->lte($now), 'future_payroll');
        $leaveService = app(LeaveService::class);
        $approvedLeaves = $leaveService->approvedDays($employee->id, $first->toDateString(), $last->toDateString());
        $scheduledDays = 0;
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) { if (in_array($day->dayOfWeek, $policy['work_days']) || !empty($approvedLeaves[$day->toDateString()])) { $scheduledDays++; } }
        D::check($scheduledDays > 0, 'work_days_required');
        $dailyRate = D::div($employee->monthly_salary, $scheduledDays);
        $records = Attendance::where('employee_id', $employee->id)->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()->keyBy('date');
        $eligible = $present = $absent = $leave = $lateMinutes = $earlyMinutes = $overtimeMinutes = 0;
        $lateAmount = $earlyAmount = $absenceAmount = '0'; $asOf = null; $pendingShift = false; $departureMinutes = 0;
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            $date = $day->toDateString();
            if ((!in_array($day->dayOfWeek, $policy['work_days']) && empty($approvedLeaves[$date])) || $date < $employee->hire_date) { continue; }
            $row = $records->get($date);
            [$start, $end] = $row?->scheduled_start ? [$row->scheduled_start, $row->scheduled_end] : $attendanceService->schedule($employee->shift, $date);
            $approved = $approvedLeaves[$date] ?? [];
            if (!$row?->scheduled_start && $approved) {
                $start = Carbon::parse($approved[0]['shift_start'], 'Asia/Amman'); $end = Carbon::parse($approved[0]['shift_end'], 'Asia/Amman');
            }
            if ($end->gt($now)) { $pendingShift = true; continue; } // Never count future shifts as absence.
            $asOf = $date; $eligible++;
            if (collect($approved)->contains('type', 'annual')) { $leave++; continue; }
            $shiftMinutes = $start->diffInMinutes($end);
            $covered = $leaveService->coveredMinutes($approved, $start, $end); $departureMinutes += $covered;
            if (!$row || $row->status === 'absent') {
                $absent++; $absenceAmount = D::add($absenceAmount, D::div(D::mul($dailyRate, $shiftMinutes - $covered), $shiftMinutes)); continue;
            }
            if ($row->status === 'leave') { $leave++; continue; } // Approved leave is paid.
            $in = $row->check_in_at; $out = $row->check_out_at;
            if (!$in && $row->check_in && $row->check_out) {
                $legacy = $attendanceService->manual(['employee_id' => $employee->id, 'date' => $date, 'status' => 'present',
                    'check_in' => substr($row->check_in, 0, 5), 'check_out' => substr($row->check_out, 0, 5)], $row);
                $in = $legacy['check_in_at']; $out = $legacy['check_out_at'];
            }
            D::check($in && $out && $out->gt($in), 'incomplete_attendance', ['date' => $date]);
            $present++;
            $hourlyRate = D::div(D::mul($dailyRate, 60), $shiftMinutes);
            $late = max(0, ($in->gt($start) ? $start->diffInMinutes($in) - $leaveService->coveredMinutes($approved, $start, $in) : 0) - (int) $policy['grace_minutes']);
            $early = $out->lt($end) ? max(0, min($shiftMinutes, $out->diffInMinutes($end)) - $leaveService->coveredMinutes($approved, $out, $end)) : 0;
            $overtime = $out->gt($end) ? $end->diffInMinutes($out) : 0;
            $lateMinutes += $late; $earlyMinutes += $early; $overtimeMinutes += $overtime;
            $lateRate = D::cmp($policy['late_hour_rate']) > 0 ? $policy['late_hour_rate'] : $hourlyRate;
            $lateAmount = D::add($lateAmount, D::div(D::mul($lateRate, $late), 60));
            $earlyAmount = D::add($earlyAmount, D::div(D::mul($hourlyRate, $early), 60));
        }
        $values = ['salary' => $this->money(D::mul($dailyRate, $eligible)), 'allowance' => $this->money(D::of($allowance)),
            'deduction' => $this->money(D::of($deduction)), 'absence_deduction' => $this->money($absenceAmount),
            'late_deduction' => $this->money($lateAmount), 'early_deduction' => $this->money($earlyAmount),
            'overtime_pay' => $this->money(D::div(D::mul($policy['overtime_hour_rate'], $overtimeMinutes), 60))];
        $beforeAdvances = D::sub(D::sum([$values['salary'], $values['allowance'], $values['overtime_pay']]),
            D::sum([$values['deduction'], $values['absence_deduction'], $values['late_deduction'], $values['early_deduction']]));
        D::check(D::cmp($beforeAdvances) >= 0, 'negative_salary');
        $remaining = $beforeAdvances; $allocations = [];
        $cutoff = $last->lt($now) ? $last->toDateString() : $now->toDateString();
        $advances = EmployeeAdvance::with('repayments')->where('employee_id', $employee->id)->where('date', '<=', $cutoff)->orderBy('date')->orderBy('id')->get();
        foreach ($advances as $advance) {
            $reserved = D::sum($advance->repayments->filter(fn ($r) => $r->payroll_id !== $existing?->id)->pluck('amount'));
            $balance = D::sub($advance->amount, $reserved);
            if (D::cmp($balance) <= 0 || D::cmp($remaining) <= 0) { continue; }
            $amount = D::cmp($remaining, $balance) >= 0 ? $balance : $remaining;
            $allocations[$advance->id] = $amount; $remaining = D::sub($remaining, $amount);
        }
        $values['advance_deduction'] = D::sum($allocations); $values['net'] = D::of($remaining);
        $values['details'] = ['scheduled_days' => $scheduledDays, 'eligible_days' => $eligible, 'present_days' => $present,
            'absent_days' => $absent, 'leave_days' => $leave, 'late_minutes' => $lateMinutes, 'early_minutes' => $earlyMinutes,
            'overtime_minutes' => $overtimeMinutes, 'daily_rate' => $dailyRate, 'monthly_salary' => $employee->monthly_salary,
            'as_of' => $asOf, 'partial' => $last->gte($now) || $pendingShift, 'policy' => $policy];
        if ($approvedLeaves) { $values['details']['approved_departure_minutes'] = $departureMinutes; }
        return [$values, $allocations];
    }

    public function save(int $employeeId, string $month, string $allowance = '0', string $deduction = '0', ?string $notes = null): Payroll
    {
        return DB::transaction(function () use ($employeeId, $month, $allowance, $deduction, $notes) {
            Context::lock(); $employee = Employee::findOrFail($employeeId);
            $payroll = Payroll::where('employee_id', $employeeId)->where('month', $month)->first();
            D::check(!$payroll?->paid_on, 'paid_payroll_locked');
            [$values, $allocations] = $this->calculate($employee, $month, $allowance, $deduction, $payroll);
            $payroll ??= new Payroll(['employee_id' => $employeeId, 'month' => $month]);
            $payroll->fill($values + ['notes' => $notes])->save();
            PayrollAdvance::where('payroll_id', $payroll->id)->delete();
            foreach ($allocations as $id => $amount) { PayrollAdvance::create(['payroll_id' => $payroll->id, 'advance_id' => $id, 'amount' => $amount]); }
            return $payroll;
        }, 3);
    }

    public function paid(Payroll $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            Context::lock(); $row = Payroll::findOrFail($payroll->id);
            if ($row->paid_on) { return; }
            D::check(!LeaveRequest::where('employee_id', $row->employee_id)->where('status', 'pending')
                ->where('start_date', '<=', Carbon::createFromFormat('!Y-m', $row->month)->endOfMonth()->toDateString())->where('end_date', '>=', $row->month.'-01')->exists(), 'pending_leave_payroll');
            D::check(Carbon::createFromFormat('!Y-m', $row->month, 'Asia/Amman')->endOfMonth()->lt(now('Asia/Amman')), 'payroll_month_open');
            [$values, $allocations] = $this->calculate($row->employee, $row->month, $row->allowance, $row->deduction, $row);
            D::check(!$values['details']['partial'], 'payroll_month_open');
            foreach ($values as $key => $value) {
                if ($key === 'details') { D::check($row->details == $value, 'payroll_stale'); }
                else { D::check(D::cmp($row->$key, $value) === 0, 'payroll_stale'); }
            }
            $reserved = PayrollAdvance::where('payroll_id', $row->id)->get()->mapWithKeys(fn ($r) => [$r->advance_id => $r->amount])->all();
            D::check($reserved == $allocations, 'payroll_stale');
            $row->update(['paid_on' => now('Asia/Amman')->toDateString()]);
        }, 3);
    }
}
