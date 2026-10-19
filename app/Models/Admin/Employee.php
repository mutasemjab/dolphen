<?php

namespace App\Models\Admin;

class Employee extends \Illuminate\Foundation\Auth\User
{
    protected $guarded = ['id'];
    protected $table = 'erp_employees';
    protected $hidden = ['password'];
    protected $casts = ['monthly_salary' => 'decimal:6', 'annual_leave_days' => 'decimal:6', 'active' => 'boolean'];
    public function shift() { return $this->belongsTo(WorkShift::class, 'shift_id'); }
    public function getLabelAttribute(): string { return (string) ($this->{'name_'.app()->getLocale()} ?: $this->name_ar); }
}
