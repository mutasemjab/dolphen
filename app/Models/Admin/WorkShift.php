<?php

namespace App\Models\Admin;

class WorkShift extends Record
{
    protected $table = 'erp_work_shifts';
    protected $casts = ['active' => 'boolean'];
}
