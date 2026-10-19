<?php

namespace App\Models\Admin;

class PayrollAdvance extends Record
{
    protected $table = 'erp_payroll_advances';
    public $timestamps = false;
    protected $casts = ['amount' => 'decimal:6'];
    public function payroll() { return $this->belongsTo(Payroll::class); }
}
