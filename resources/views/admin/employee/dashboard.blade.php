@extends('admin.employee.layout')
@section('title', $employee->label)
@section('subtitle', __('erp.employee_portal'))
@section('actions')<a class="btn primary" href="{{ route('employee.leaves') }}">{{ __('erp.my_leave_requests') }}</a>@endsection
@section('content')
<section class="panel form-panel"><strong>{{ __('erp.leave_available') }} ({{ $leaveBalance['year'] }}): {{ \App\Support\Admin\Format::number($leaveBalance['available'], 3) }} {{ __('erp.days_unit') }}</strong>
    <a class="btn small" href="{{ route('employee.leaves') }}">{{ __('erp.request_leave') }}</a>
</section>
<section class="panel form-panel">
    <h2>{{ __('erp.attendance') }}</h2>
    <p>{{ __('erp.shift_id') }}: {{ $employee->shift?->label ?: '—' }}
        @if($employee->shift)<b dir="ltr">{{ substr($employee->shift->starts_at,0,5) }} — {{ substr($employee->shift->ends_at,0,5) }}</b>@endif
    </p>
    @if($open)<p>{{ __('erp.checked_in_at') }}: <b>{{ $open->check_in_at->format('Y-m-d H:i') }}</b></p>@endif
    <p class="hint">{{ __('erp.punch_help', ['radius'=>$policy['radius_meters']]) }}</p>
    <form method="post" action="{{ route('employee.attendance') }}" data-location-form data-error="{{ __('erp.location_browser_error') }}" data-pending="{{ __('erp.locating') }}">@csrf
        <input type="hidden" name="action" value="{{ $open ? 'out' : 'in' }}">
        @foreach(['latitude','longitude','accuracy'] as $field)<input type="hidden" name="{{ $field }}">@endforeach
        <button class="btn primary full-width">{{ __('erp.'.($open ? 'record_check_out' : 'record_check_in')) }}</button>
        <p data-location-message role="status" aria-live="polite"></p>
    </form>
</section>
<section class="panel"><h2>{{ __('erp.my_attendance') }}</h2>
    <div class="table-wrap"><table><thead><tr>@foreach(['date','status','check_in','check_out'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach</tr></thead><tbody>
        @forelse($attendance as $row)<tr><td><bdi>{{ $row->date }}</bdi></td><td>{{ __('erp.'.$row->status) }}</td><td><bdi>{{ $row->check_in_at?->format('m-d H:i') ?: $row->check_in ?: '—' }}</bdi></td><td><bdi>{{ $row->check_out_at?->format('m-d H:i') ?: $row->check_out ?: '—' }}</bdi></td></tr>
        @empty<tr><td colspan="4" class="empty">{{ __('erp.no_data') }}</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="panel"><h2>{{ __('erp.my_salaries') }}</h2>
    <div class="table-wrap"><table><thead><tr>@foreach(['month','net','paid_on'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach</tr></thead><tbody>
        @forelse($payrolls as $row)<tr><td><a href="{{ route('employee.payslip',$row->id) }}">{{ $row->month }}</a> @if($row->details['partial'] ?? false) · {{ __('erp.partial_payroll') }} @endif</td><td>{{ \App\Support\Admin\Format::money($row->net) }}</td><td>{{ $row->paid_on ?: __('erp.unpaid') }}</td></tr>
        @empty<tr><td colspan="3" class="empty">{{ __('erp.no_data') }}</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="panel"><h2>{{ __('erp.my_advances') }}</h2><div class="table-wrap"><table><thead><tr>@foreach(['date','amount','remaining'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach</tr></thead><tbody>
    @forelse($advances as $row)
    @php($repaid = \App\Support\Admin\Decimal::sum($row->repayments->filter(fn($r)=>$r->payroll->paid_on)->pluck('amount')))
    <tr><td>{{ $row->date }}</td><td>{{ \App\Support\Admin\Format::money($row->amount) }}</td><td>{{ \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($row->amount,$repaid)) }}</td></tr>
    @empty<tr><td colspan="3" class="empty">{{ __('erp.no_data') }}</td></tr>@endforelse
</tbody></table></div></section>
@endsection
