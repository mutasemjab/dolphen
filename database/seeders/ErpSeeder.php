<?php

namespace Database\Seeders;

use App\Models\Admin\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

class ErpSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = ['erp.dashboard.view'];
        foreach (['sales', 'purchasing', 'products', 'inventory', 'hr'] as $module) {
            foreach (['view', 'manage'] as $action) { $permissions[] = 'erp.'.$module.'.'.$action; }
        }
        foreach ($permissions as $permission) { Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']); }
        foreach ([
            'erp_super_admin' => $permissions,
            'erp_staff' => array_values(array_filter($permissions, fn ($p) => !str_starts_with($p, 'erp.hr.'))),
            'erp_hr' => ['erp.dashboard.view', 'erp.hr.view', 'erp.hr.manage'],
        ] as $name => $grants) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
            if ($role->wasRecentlyCreated) { $role->syncPermissions($grants); }
        }
        foreach (['piece' => ['قطعة', 'Piece'], 'kg' => ['كيلو', 'Kilogram'], 'roll' => ['رول', 'Roll']] as $code => [$ar, $en]) {
            Unit::firstOrCreate(['code' => $code], ['name_ar' => $ar, 'name_en' => $en]);
        }
        foreach (['posting_mutex' => '1', 'company_name_ar' => 'مصنع عايده الحواجرة للبلاستيك', 'company_name_en' => 'Aida Al-Hawajreh Plastic Factory', 'tax_number' => '100335976', 'currency' => 'JOD', 'currency_decimals' => '3'] as $key => $value) {
            DB::table('erp_settings')->insertOrIgnore(compact('key', 'value'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
