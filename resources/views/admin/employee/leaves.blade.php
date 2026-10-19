@extends('admin.employee.layout')
@section('title', __('erp.my_leave_requests'))
@section('actions')<a class="btn" href="{{ route('employee.dashboard') }}">{{ __('erp.back') }}</a>@endsection
@section('content')
<form class="filters"><label>{{ __('erp.leave_year') }}<input type="number" name="year" value="{{ $year }}" min="2000" max="2100" required></label><button class="btn">{{ __('erp.filter') }}</button></form>
@include('admin.hr.leave-balance')
<section class="panel form-panel"><h2>{{ __('erp.request_leave') }}</h2><p class="hint">{{ __('erp.leave_request_help') }}</p>
    <form method="post" action="{{ route('employee.leaves.store') }}" data-leave-form>@csrf
        <input type="hidden" name="request_id" value="{{ old('request_id',(string) \Illuminate\Support\Str::uuid()) }}">
        <div class="form-grid">
            @include('admin.field',['field'=>'type','rule'=>'required','choices'=>['annual'=>__('erp.annual'),'departure'=>__('erp.departure')]])
            @include('admin.field',['field'=>'start_date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
            @include('admin.field',['field'=>'end_date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
        </div>
        <div class="form-grid" data-departure-times>
            @include('admin.field',['field'=>'from_time','rule'=>'nullable|date_format:H:i'])
            @include('admin.field',['field'=>'to_time','rule'=>'nullable|date_format:H:i'])
        </div>
        @include('admin.field',['field'=>'reason','rule'=>'required|string'])
        <button class="btn primary">{{ __('erp.submit_request') }}</button>
    </form>
</section>
<section class="panel"><div class="table-wrap"><table><thead><tr>
    @foreach(['type','leave_period','charged_days','status','reason','review_note','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach
</tr></thead><tbody>
    @forelse($requests as $row)<tr>
        <td>{{ __('erp.'.$row->type) }}</td><td><bdi>{{ $row->start_date }}</bdi>@if($row->end_date !== $row->start_date) — <bdi>{{ $row->end_date }}</bdi>@endif
            @if($row->type === 'departure')<br><bdi>{{ substr($row->from_time,0,5) }} — {{ substr($row->to_time,0,5) }}</bdi>@endif</td>
        <td>{{ \App\Support\Admin\Format::number($row->charged_days,3) }}</td><td><span class="badge {{ $row->status }}">{{ __('erp.'.$row->status) }}</span></td><td>{{ $row->reason }}</td><td>{{ $row->review_note ?: '—' }}</td>
        <td>@if($row->status === 'pending')<form method="post" action="{{ route('employee.leaves.cancel',$row->id) }}" data-confirm="{{ __('erp.cancel_leave_confirm') }}">@csrf<button class="btn small">{{ __('erp.cancel_request') }}</button></form>@endif</td>
    </tr>@empty<tr><td colspan="7" class="empty">{{ __('erp.no_data') }}</td></tr>@endforelse
</tbody></table></div>{{ $requests->links('admin.pagination') }}</section>
@endsection
