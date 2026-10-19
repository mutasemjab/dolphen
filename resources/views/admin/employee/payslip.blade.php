@extends('admin.employee.layout')
@section('title', __('erp.payslip').' · '.$payroll->month)
@section('actions')<button type="button" class="btn" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('employee.dashboard') }}">{{ __('erp.back') }}</a>@endsection
@section('content')@include('admin.hr.payroll-detail')@endsection
