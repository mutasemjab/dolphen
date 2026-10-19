<?php

namespace App\Models\Admin;

class Classification extends Record
{
    protected $table = 'erp_classifications';
    protected $casts = ['active' => 'boolean'];
}
