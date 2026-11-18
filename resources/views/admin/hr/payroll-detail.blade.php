<section class="panel form-panel salary-breakdown">
    <div class="document-meta"><div><span>{{ __('erp.employee_id') }}</span><strong>{{ $payroll->employee->label }}</strong></div><div><span>{{ __('erp.month') }}</span><strong>{{ $payroll->month }}</strong></div><div><span>{{ __('erp.paid_on') }}</span><strong>{{ $payroll->paid_on ?: __('erp.unpaid') }}</strong></div></div>
    @if($payroll->details)
    <p class="hint">{{ __('erp.calculated_through') }}: {{ $payroll->details['as_of'] ?: '—' }} @if($payroll->details['partial']) · {{ __('erp.partial_payroll') }} @endif</p>
    <div class="table-wrap"><table><tbody>
        @foreach(['scheduled_days','eligible_days','present_days','absent_days','leave_days','late_minutes','early_minutes','overtime_minutes'] as $field)
        <tr><th>{{ __('erp.'.$field) }}</th><td>{{ $payroll->details[$field] }}</td></tr>
        @endforeach
        @if(isset($payroll->details['holiday_overtime_minutes']))
        @foreach(['holiday_days','holiday_worked_days','holiday_overtime_minutes'] as $field)
        <tr><th>{{ __('erp.'.$field) }}</th><td>{{ $payroll->details[$field] }}</td></tr>
        @endforeach
        <tr><th>{{ __('erp.worker_type') }}</th><td>{{ __('erp.'.$payroll->details['worker_type']) }}</td></tr>
        <tr><th>{{ __('erp.overtime_rate') }}</th><td>{{ \App\Support\Admin\Format::money($payroll->details['overtime_rate']) }}</td></tr>
        <tr><th>{{ __('erp.holiday_overtime_rate') }}</th><td>{{ \App\Support\Admin\Format::money($payroll->details['holiday_overtime_rate']) }}</td></tr>
        @endif
        <tr><th>{{ __('erp.daily_rate') }}</th><td>{{ \App\Support\Admin\Format::money($payroll->details['daily_rate']) }}</td></tr>
        @if(isset($payroll->details['approved_departure_minutes']))<tr><th>{{ __('erp.approved_departure_minutes') }}</th><td>{{ $payroll->details['approved_departure_minutes'] }}</td></tr>@endif
    </tbody></table></div>
    @endif
    <div class="table-wrap"><table><tbody>
        @foreach(['salary','allowance','overtime_pay','holiday_overtime_pay','absence_deduction','late_deduction','early_deduction','deduction','advance_deduction','net'] as $field)
        <tr><th>{{ __('erp.'.$field) }}</th><td>{{ \App\Support\Admin\Format::money($payroll->$field) }} {{ \App\Services\Admin\Context::setting('currency','JOD') }}</td></tr>
        @endforeach
    </tbody></table></div><p>{{ $payroll->notes }}</p>
</section>
