<?php

use App\Models\Admin as M;

return [
    'masters' => [
        'products' => ['model' => M\Product::class, 'module' => 'products', 'fields' => [
            'name_ar' => 'required|string|max:200', 'item_type' => 'required|in:raw,finished',
            'classification_id' => 'nullable|integer|exists:erp_classifications,id', 'warehouse_id' => 'required|integer|exists:erp_warehouses,id',
            'base_unit_id' => 'required|exists:erp_units,id', 'cost' => 'required|numeric|min:0',
            // Raw materials are not sold, so they carry no selling price.
            'selling_price' => 'required_if:item_type,finished|nullable|numeric|min:0', 'minimum_stock' => 'required|numeric|min:0', 'active' => 'required|boolean',
        ]],
        'units' => ['model' => M\Unit::class, 'module' => 'products'],
        'warehouses' => ['model' => M\Warehouse::class, 'module' => 'inventory'],
        'customers' => ['model' => M\Partner::class, 'module' => 'sales', 'scope' => ['kind' => 'customer'], 'fields' => [
            'name_ar' => 'required|string|max:200', 'classification_id' => 'nullable|integer|exists:erp_classifications,id',
            'phone' => 'nullable|string|max:100', 'address' => 'nullable|string|max:2000', 'active' => 'required|boolean', 'notes' => 'nullable|string|max:2000',
        ]],
        'suppliers' => ['model' => M\Partner::class, 'module' => 'purchasing', 'scope' => ['kind' => 'supplier'], 'fields' => [
            'name_ar' => 'required|string|max:200', 'classification_id' => 'nullable|integer|exists:erp_classifications,id',
            'phone' => 'nullable|string|max:100', 'address' => 'nullable|string|max:2000', 'active' => 'required|boolean', 'notes' => 'nullable|string|max:2000',
        ]],
        'currencies' => ['model' => M\Currency::class, 'module' => 'sales', 'fields' => [
            'code' => 'required|string|regex:/^[A-Za-z]{3}$/|unique:erp_currencies,code', 'name_ar' => 'required|string|max:200',
            'symbol' => 'nullable|string|max:10', 'decimals' => 'required|integer|min:0|max:6',
            'exchange_rate' => 'required|numeric|gt:0', 'active' => 'required|boolean',
        ]],
        'product_categories' => ['model' => M\Classification::class, 'module' => 'products', 'scope' => ['kind' => 'product'], 'fields' => ['name_ar' => 'required|string|max:200', 'active' => 'required|boolean']],
        'customer_categories' => ['model' => M\Classification::class, 'module' => 'sales', 'scope' => ['kind' => 'customer'], 'fields' => ['name_ar' => 'required|string|max:200', 'active' => 'required|boolean']],
        'supplier_categories' => ['model' => M\Classification::class, 'module' => 'purchasing', 'scope' => ['kind' => 'supplier'], 'fields' => ['name_ar' => 'required|string|max:200', 'active' => 'required|boolean']],
        'shifts' => ['model' => M\WorkShift::class, 'module' => 'hr', 'fields' => [
            'name_ar' => 'required|string|max:200', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|different:starts_at', 'active' => 'required|boolean',
        ]],
        'employees' => ['model' => M\Employee::class, 'module' => 'hr', 'extra' => [
            'phone' => 'nullable|string|max:100', 'job_title' => 'nullable|string|max:200',
            'hire_date' => 'required|date_format:Y-m-d', 'monthly_salary' => 'required|numeric|min:0', 'worker_type' => 'required|in:local,foreign',
            'annual_leave_days' => 'sometimes|required|numeric|min:0|max:366',
            'shift_id' => 'required|integer|exists:erp_work_shifts,id', 'username' => 'nullable|alpha_dash|max:100|unique:erp_employees,username',
            'password' => 'nullable|string|min:6|max:200|confirmed', 'password_confirmation' => 'nullable|string|max:200',
        ]],
        'attendance' => ['model' => M\Attendance::class, 'module' => 'hr', 'fields' => [
            'employee_id' => 'required|exists:erp_employees,id', 'date' => 'required|date_format:Y-m-d',
            'status' => 'required|in:present,absent,leave', 'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i', 'notes' => 'nullable|string|max:2000',
        ]],
    ],
    'relations' => [
        'base_unit_id' => M\Unit::class, 'product_id' => M\Product::class,
        'warehouse_id' => M\Warehouse::class, 'partner_id' => M\Partner::class, 'employee_id' => M\Employee::class, 'shift_id' => M\WorkShift::class,
    ],
];
