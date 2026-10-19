<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Admin;
use App\Services\Admin\Context;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show_login_view()
    {
        return view('admin.auth.factory-login');
    }

    public function login(LoginRequest $request)
    {
        if (auth('admin')->attempt($request->only('username', 'password'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }
        return back()->withErrors(['username' => __('auth.failed')])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        auth('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.showlogin');
    }

    public function editlogin(int $id)
    {
        abort_unless($id === auth('admin')->id(), 403);
        return view('admin.auth.profile', ['data' => Admin::findOrFail($id)]);
    }

    public function updatelogin(Request $request, int $id)
    {
        abort_unless($id === auth('admin')->id(), 403);
        $data = $request->validate(['username' => 'required|string|max:100|unique:admins,username,'.$id, 'password' => 'required|string|min:6|confirmed', 'current_password' => 'required|current_password:admin']);
        $admin = Admin::findOrFail($id);
        $admin->update(['username' => $data['username'], 'password' => Hash::make($data['password'])]);
        Context::audit($admin, 'profile_credentials_updated', $admin);
        return $this->logout($request);
    }
}