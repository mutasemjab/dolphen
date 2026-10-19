@extends('admin.layouts.factory')
@section('title', __('erp.hr_settings'))
@section('actions')<a class="btn primary" href="{{ route('admin.masters.index','shifts') }}">{{ __('erp.shifts') }}</a><a class="btn" href="{{ route('employee.login') }}" target="_blank" rel="noopener">{{ __('erp.employee_portal') }}</a>@endsection
@section('content')
<form class="panel form-panel" method="post" action="{{ route('admin.hr.settings.save') }}">@csrf
    <h2>{{ __('erp.attendance_location') }}</h2><p class="hint">{{ __('erp.location_help') }}</p>
    <div class="form-grid">
        @foreach(['latitude','longitude','radius_meters'] as $field)
        @include('admin.field',['field'=>$field,'rule'=>'required|numeric','value'=>$settings[$field]])
        @endforeach
    </div>
    <button class="btn" type="button" data-locate-settings data-error="{{ __('erp.location_browser_error') }}">{{ __('erp.use_my_location') }}</button>
    <p role="status" data-location-message></p>
    <h2>{{ __('erp.work_days') }}</h2><p class="hint">{{ __('erp.salary_basis_help') }}</p>
    <div class="work-days">
        @foreach([6,0,1,2,3,4,5] as $day)
        <label><input type="checkbox" name="work_days[]" value="{{ $day }}" @checked(in_array($day, old('work_days',$settings['work_days'])))> {{ __('erp.weekday_'.$day) }}</label>
        @endforeach
    </div>
    <h2>{{ __('erp.lateness_overtime') }}</h2>
    <div class="form-grid">
        @foreach(['grace_minutes','late_hour_rate','overtime_hour_rate'] as $field)
        @include('admin.field',['field'=>$field,'rule'=>'required|numeric','value'=>$settings[$field]])
        @endforeach
    </div>
    <p class="hint">{{ __('erp.lateness_help') }}</p>
    <p class="hint">{{ __('erp.settings_history_help') }}</p>
    <div class="form-footer"><button class="btn primary">{{ __('erp.save') }}</button></div>
</form>
@endsection
