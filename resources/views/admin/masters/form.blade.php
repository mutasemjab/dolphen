@extends('admin.layouts.factory')
@section('title', __('erp.'.$resource).' · '.__('erp.'.($record->exists ? 'edit' : 'create')))
@section('content')
<form method="post" class="panel form-panel" action="{{ $record->exists ? route('admin.masters.update',[$resource,$record->id]) : route('admin.masters.store',$resource) }}">
    @csrf
    @if($record->exists)
    @method('put')
    @endif
    @if($resource === 'employees')<p class="hint">{{ __('erp.employee_account_help') }} <a href="{{ route('employee.login') }}" target="_blank" rel="noopener">{{ __('erp.employee_portal') }}</a></p>@endif
    @if($resource === 'employees')<p class="hint">{{ __('erp.annual_balance_help') }}</p>@endif
    @if($resource === 'shifts')<p class="hint">{{ __('erp.shift_help') }}</p>@endif
    @if($resource === 'attendance')<p class="hint">{{ __('erp.attendance_help') }}</p>@endif
    <div class="form-grid">
    @foreach($definition['fields'] as $field => $rule)
        @include('admin.field',['field'=>$field,'rule'=>$rule,'record'=>$record,'options'=>$options,'value'=>$record->exists ? $record->$field : (str_contains($rule,'numeric') ? '0' : null)])
    @endforeach
    </div>
    <div class="form-footer"><button class="btn primary">{{ __('erp.save') }}</button><a class="btn" href="{{ route('admin.masters.index',$resource) }}">{{ __('erp.back') }}</a></div>
</form>
@endsection
