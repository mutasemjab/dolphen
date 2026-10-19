<?php

namespace App\Models\Admin;

class Attendance extends Record
{
    protected $table = 'erp_attendance';

    protected $casts = ['scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
