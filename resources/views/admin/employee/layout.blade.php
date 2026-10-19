<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') · {{ __('erp.factory') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/admin/css/factory.css') }}">
    <script defer src="{{ asset('assets/admin/js/factory.js') }}"></script>
</head>
<body><div class="workspace guest-workspace employee-workspace">
    <header class="topbar"><a href="{{ route('employee.dashboard') }}">{{ __('erp.employee_portal') }}</a><div class="topbar-actions">
        <form method="post" action="{{ route('admin.language',app()->getLocale() === 'ar' ? 'en' : 'ar') }}">@csrf<button class="language">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</button></form>
        @auth('employee')<form method="post" action="{{ route('employee.logout') }}">@csrf<button class="language">{{ __('erp.logout') }}</button></form>@endauth
    </div></header>
    <main><div class="page-heading"><div><h1>@yield('title')</h1><p>@yield('subtitle')</p></div><div class="actions no-print">@yield('actions')</div></div>
        @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div></body></html>
