@extends('admin.layouts.factory')
@section('title', __('erp.payslip').' · '.$payroll->employee->label)
@section('actions')<button type="button" class="btn" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('admin.payroll.index',['month'=>$payroll->month]) }}">{{ __('erp.back') }}</a>
@endsection
@section('content')
@include('admin.hr.payroll-detail')
@endsection
