
@extends('admin.layouts.factory')
@section('title', __('erp.sign_in'))
@section('subtitle', __('erp.login_subtitle'))
@section('content')<section class="login-card panel"><div class="login-brand"><span class="brand-mark">D</span><h2>{{ __('erp.factory') }}</h2><p>{{ __('erp.manufacturing_erp') }}</p></div><form method="post" action="{{ route('admin.login') }}">
@csrf<div class="field"><label for="username">{{ __('erp.username') }}</label><input id="username" name="username" value="{{ old('username') }}" required autocomplete="username" autofocus>
@error('username')<span class="field-error">{{ $message }}</span>
@enderror</div><div class="field"><label for="password">{{ __('erp.password') }}</label><input type="password" id="password" name="password" required autocomplete="current-password">
@error('password')<span class="field-error">{{ $message }}</span>
@enderror</div><button class="btn primary full-width">{{ __('erp.sign_in') }}</button></form><p><a href="{{ route('employee.login') }}">{{ __('erp.employee_portal') }}</a></p></section>
@endsection
