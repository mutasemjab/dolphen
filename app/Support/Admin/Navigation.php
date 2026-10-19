<?php

namespace App\Support\Admin;

class Navigation
{
    public static function groups(): array
    {
        $master = fn ($key) => [__('erp.'.$key), route('admin.masters.index', $key), 'erp.'.config('erp.masters.'.$key.'.module').'.view'];
        return [
            'dashboard' => [[__('erp.overview'), route('admin.dashboard'), 'erp.dashboard.view']],
            'sales' => [[__('erp.sales_invoice'), route('admin.invoices.index', 'sales_invoice'), 'erp.sales.view'], $master('customers')],
            'purchasing' => [[__('erp.purchase_invoice'), route('admin.invoices.index', 'purchase_invoice'), 'erp.purchasing.view'], $master('suppliers')],
            'inventory' => [[__('erp.stock'), route('admin.stock'), 'erp.inventory.view'], $master('products'), $master('warehouses'), $master('units')],
            'hr' => [$master('employees'), $master('attendance'), [__('erp.leave_requests'), route('admin.hr.leaves'), 'erp.hr.view'], [__('erp.advances'), route('admin.hr.advances'), 'erp.hr.view'],
                [__('erp.payroll'), route('admin.payroll.index'), 'erp.hr.view'], [__('erp.hr_settings'), route('admin.hr.settings'), 'erp.hr.manage']],
        ];
    }
}
