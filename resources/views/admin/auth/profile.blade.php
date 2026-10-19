@extends('admin.layouts.factory')
@section('title', __('erp.profile'))
@section('content')
<form method="post" action="{{ route('admin.login.update',$data->id) }}" class="panel form-panel">
@csrf
<div class="form-grid">
@include('admin.field',['field'=>'username','value'=>$data->username,'rule'=>'required'])
@foreach(['current_password','password','password_confirmation'] as $field)
<div class="field"><label for="{{ $field }}">{{ __('erp.'.$field) }}</label><input id="{{ $field }}" type="password" name="{{ $field }}" required @if($field !== 'current_password') minlength="6" @endif autocomplete="{{ $field==='current_password'?'current-password':'new-password' }}"></div>
@endforeach
</div><button class="btn primary">{{ __('erp.save') }}</button></form>
@endsection
