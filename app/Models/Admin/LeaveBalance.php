<?php

namespace App\Models\Admin;

class LeaveBalance extends Record
{
    protected $table = 'erp_leave_balances';
    protected $casts = ['allowance' => 'decimal:6'];
}
