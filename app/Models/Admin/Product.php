<?php

namespace App\Models\Admin;

class Product extends Record
{
    protected $table = 'erp_products';
    protected $casts = ['cost' => 'decimal:6', 'selling_price' => 'decimal:6', 'minimum_stock' => 'decimal:6', 'quantity' => 'decimal:6', 'active' => 'boolean'];
    public function baseUnit() { return $this->belongsTo(Unit::class, 'base_unit_id'); }
}
