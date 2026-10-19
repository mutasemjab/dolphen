<?php

namespace App\Models\Admin;

class EmployeeAdvance extends Record
{
    protected $table = 'erp_employee_advances';
    protected $casts = ['amount' => 'decimal:6'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function repayments() { return $this->hasMany(PayrollAdvance::class, 'advance_id'); }
}
