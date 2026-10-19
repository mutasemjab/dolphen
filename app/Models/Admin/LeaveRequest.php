<?php

namespace App\Models\Admin;

class LeaveRequest extends Record
{
    protected $table = 'erp_leave_requests';
    protected $casts = ['days' => 'array', 'charged_days' => 'decimal:6', 'reviewed_at' => 'datetime'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(\App\Models\Admin::class, 'reviewed_by'); }
}
