<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EmployeeSession
{
    public function handle($request, Closure $next)
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee || !$employee->active || !$employee->username || !$employee->password ||
            !hash_equals(hash('sha256', $employee->password), (string) $request->session()->get('employee_auth_hash', ''))) {
            Auth::guard('employee')->logout(); $request->session()->forget('employee_auth_hash');
            return redirect()->route('employee.login');
        }
        Auth::shouldUse('employee');
        return $next($request);
    }
}
