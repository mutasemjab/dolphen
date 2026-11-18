@php($locale = app()->getLocale())
@php($companyName = auth('admin')->check() ? \App\Services\Admin\Context::setting('company_name_'.$locale, __('erp.factory')) : __('erp.factory'))
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') · {{ $companyName }}</title>
    <link rel="stylesheet" href="{{ asset('assets/admin/vendor/bootstrap'.($locale === 'ar' ? '.rtl' : '').'.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/factory.css') }}">
    <script defer src="{{ asset('assets/admin/vendor/jquery.min.js') }}"></script>
    <script defer src="{{ asset('assets/admin/vendor/select2/select2.min.js') }}"></script>
    <script defer src="{{ asset('assets/admin/vendor/select2/ar.js') }}"></script>
    <script defer src="{{ asset('assets/admin/js/factory.js') }}"></script>
</head>
<body>
@auth('admin')
<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('admin.dashboard') }}"><span class="brand-mark">D</span><span>{{ $companyName }}</span></a>
    <nav aria-label="{{ __('erp.navigation') }}">
        @foreach(\App\Support\Admin\Navigation::groups() as $group => $links)
            @php($visible = array_filter($links, fn($link) => auth('admin')->user()->can($link[2])))
            @if(count($visible))
            <div class="nav-section"><span>{{ __('erp.'.$group) }}</span>
                @foreach($visible as $link)
                <a class="{{ url()->current() === $link[1] ? 'active' : '' }}" href="{{ $link[1] }}">{{ $link[0] }}</a>
                @endforeach
            </div>
            @endif
        @endforeach
        @if(auth('admin')->user()->is_super)
        <div class="nav-section"><a href="{{ route('admin.access') }}">{{ __('erp.users') }}</a></div>
        @endif
    </nav>
</aside>
@endauth
<div class="workspace {{ auth('admin')->guest() ? 'guest-workspace' : '' }}">
    <header class="topbar">
        <div class="topbar-left">
            @auth('admin')
            <button type="button" class="icon-button" id="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="{{ __('erp.navigation') }}">☰</button>
            @endauth
          
        </div>
        <div class="topbar-actions">
            <form method="post" action="{{ route('admin.language', $locale === 'ar' ? 'en' : 'ar') }}">@csrf<button class="language">{{ $locale === 'ar' ? 'English' : 'العربية' }}</button></form>
            @auth('admin')
            <a class="user-name" href="{{ route('admin.login.edit', auth('admin')->id()) }}">{{ auth('admin')->user()->name }}</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="language">{{ __('erp.logout') }}</button></form>
            @endauth
        </div>
    </header>
    <main>
        <div class="page-heading"><div><h1>@yield('title')</h1><p>@yield('subtitle')</p></div><div class="actions no-print">@yield('actions')</div></div>
        @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
        <div class="alert danger" role="alert"><strong>{{ __('erp.correct_errors') }}</strong><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>
            @endforeach
        </ul></div>
        @endif
        @yield('content')
        <footer class="footer">{{ $companyName }}</footer>
    </main>
</div>
</body>
</html>
