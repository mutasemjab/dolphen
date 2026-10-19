<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\Context;
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccessController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->is_super, 403);
        return view('admin.access', ['users' => Admin::with('roles')->orderBy('id')->paginate(20)]);
    }

    public function save(Request $request, ?int $user = null)
    {
        abort_unless($request->user('admin')->is_super, 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'username' => 'required|string|max:100|unique:admins,username'.($user ? ','.$user : ''),
            'password' => ($user ? 'nullable' : 'required').'|string|min:6|confirmed', 'role' => 'required|in:erp_staff,erp_hr']);
        DB::transaction(function () use ($request, $data, $user) {
            Context::lock(); $target = $user ? Admin::lockForUpdate()->findOrFail($user) : new Admin();
            D::check(!$target->is_super && $target->id !== $request->user('admin')->id, 'self_privilege_change');
            $target->fill(['name' => $data['name'], 'username' => $data['username']]);
            if (!empty($data['password'])) { $target->password = Hash::make($data['password']); }
            $target->save(); $target->syncRoles([$data['role']]);
        }, 3);
        return redirect()->route('admin.access')->with('success', __('erp.saved'));
    }
}
