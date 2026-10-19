<?php

namespace App\Http\Middleware;

use Closure;

class AdminLocale
{
    public function handle($request, Closure $next)
    {
        $locale = $request->session()->get('erp_locale', 'ar');
        app()->setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : 'ar');
        config(['app.timezone' => 'Asia/Amman']);
        date_default_timezone_set('Asia/Amman');
        return $next($request);
    }
}
