<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin;
use App\Services\Admin\Context;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateFactoryAdmin extends Command
{
    protected $signature = 'admin:create {username? : Unique login name}';
    protected $description = 'Create a factory administrator using an interactively entered password';
    public function handle(): int
    {
        if (!Role::where('name', 'erp_super_admin')->where('guard_name', 'admin')->exists()) {
            $this->error('Run migrations and ErpSeeder first.'); return 1;
        }
        $username = $this->argument('username') ?: $this->ask('Username');
        $data = ['username' => $username, 'name' => $this->ask('Name'), 'email' => $this->ask('Email (optional)'), 'password' => $this->secret('Password (at least 6 characters)'), 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['username' => 'required|string|max:100|unique:admins,username', 'name' => 'required|string|max:100', 'email' => 'nullable|email|max:100', 'password' => 'required|string|min:6|confirmed']);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) { $this->error($error); } return 1; }
        DB::transaction(function () use ($data) {
            Context::lock();
            $admin = new Admin(); $admin->fill(array_intersect_key($data, array_flip(['username', 'name', 'email'])));
            $admin->password = Hash::make($data['password']); $admin->is_super = true; $admin->save(); $admin->assignRole('erp_super_admin');
            Context::audit($admin, 'administrator_created', $admin);
        }, 3);
        $this->info('Administrator created. Open /admin/login.'); return 0;
    }
}
