@extends('admin.employee.layout')
@section('title', __('erp.employee_portal'))
@section('content')
<section class="panel login-card"><h2>{{ __('erp.sign_in') }}</h2>
    <form method="post" action="{{ route('employee.login.submit') }}">@csrf
        <div class="field"><label for="employee-username">{{ __('erp.username') }}</label><input id="employee-username" name="username" value="{{ old('username') }}" required autocomplete="username" autofocus></div>
        <div class="field"><label for="employee-password">{{ __('erp.password') }}</label><input type="password" id="employee-password" name="password" required autocomplete="current-password"></div>
        <button class="btn primary full-width">{{ __('erp.sign_in') }}</button>
    </form>
</section>
@endsection
