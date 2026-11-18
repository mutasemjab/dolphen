<?php

namespace App\Models\Admin;

class Payroll extends Record
{
    protected $table = 'erp_payrolls';
    protected $casts = ['salary' => 'decimal:6', 'allowance' => 'decimal:6', 'deduction' => 'decimal:6', 'net' => 'decimal:6',
        'overtime_pay' => 'decimal:6', 'absence_deduction' => 'decimal:6', 'late_deduction' => 'decimal:6',
        'early_deduction' => 'decimal:6', 'advance_deduction' => 'decimal:6', 'holiday_overtime_pay' => 'decimal:6', 'details' => 'array'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
