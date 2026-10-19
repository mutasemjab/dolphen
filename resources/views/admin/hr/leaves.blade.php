@extends('admin.layouts.factory')
@section('title', __('erp.leave_requests'))
@section('content')
<section class="panel"><form class="filters">
    <label>{{ __('erp.leave_year') }}<input type="number" name="year" min="2000" max="2100" value="{{ $year }}" required></label>
    <label>{{ __('erp.employee_id') }}<select name="employee_id"><option value="">{{ __('erp.all') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->label }}</option>@endforeach</select></label>
    <label>{{ __('erp.status') }}<select name="status"><option value="">{{ __('erp.all') }}</option>@foreach(['pending','approved','rejected','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('erp.'.$status) }}</option>@endforeach</select></label>
    <button class="btn">{{ __('erp.filter') }}</button>
    @if(request('employee_id'))@can('erp.hr.manage')<a class="btn" href="{{ route('admin.masters.edit',['employees',request('employee_id')]) }}">{{ __('erp.edit_employee_balance') }}</a>@endcan @endif
</form></section>
@if($balance)@include('admin.hr.leave-balance')@endif
<section class="panel"><p class="hint leave-intro">{{ __('erp.leave_review_help') }}</p><div class="table-wrap"><table><thead><tr>
    @foreach(['employee_id','type','leave_period','charged_days','status','reason','review_note','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach
</tr></thead><tbody>
    @forelse($requests as $row)<tr>
        <td>{{ $row->employee->label }}</td><td>{{ __('erp.'.$row->type) }}</td><td><bdi>{{ $row->start_date }}</bdi>@if($row->end_date !== $row->start_date)<br><bdi>{{ $row->end_date }}</bdi>@endif
            @if($row->type === 'departure')<br><bdi>{{ substr($row->from_time,0,5) }} — {{ substr($row->to_time,0,5) }}</bdi>@endif</td>
        <td>{{ \App\Support\Admin\Format::number($row->charged_days,3) }}</td><td><span class="badge {{ $row->status }}">{{ __('erp.'.$row->status) }}</span></td>
        <td>{{ $row->reason }}</td><td>{{ $row->review_note ?: '—' }} @if($row->reviewer)<small class="reviewer">{{ $row->reviewer->name }} · <bdi>{{ $row->reviewed_at?->format('Y-m-d H:i') }}</bdi></small>@endif</td>
        <td>@can('erp.hr.manage')
        @if($row->status === 'pending')
            <form method="post" action="{{ route('admin.hr.leaves.review',$row) }}" class="leave-review">@csrf
                <input name="review_note" placeholder="{{ __('erp.review_note') }}" aria-label="{{ __('erp.review_note') }}" maxlength="2000">
                <select name="status" aria-label="{{ __('erp.review_decision') }}" required><option value="approved">{{ __('erp.approve_request') }}</option><option value="rejected">{{ __('erp.reject_request') }}</option></select>
                <button class="btn primary small">{{ __('erp.save_decision') }}</button>
            </form>
        @elseif($row->status === 'approved')
            <form method="post" action="{{ route('admin.hr.leaves.review',$row) }}" data-confirm="{{ __('erp.cancel_leave_confirm') }}">@csrf<input type="hidden" name="status" value="cancelled"><button class="btn small">{{ __('erp.cancel_request') }}</button></form>
        @endif
        @endcan</td>
    </tr>@empty<tr><td colspan="8" class="empty">{{ __('erp.no_data') }}</td></tr>@endforelse
</tbody></table></div>{{ $requests->links('admin.pagination') }}</section>
@endsection
