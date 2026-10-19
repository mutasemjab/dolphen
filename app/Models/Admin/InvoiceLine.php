<?php

namespace App\Models\Admin;

class InvoiceLine extends Record
{
    protected $table = 'erp_document_lines';
    protected $casts = ['quantity' => 'decimal:6', 'base_quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'net' => 'decimal:6', 'total' => 'decimal:6'];
    public function invoice() { return $this->belongsTo(Invoice::class, 'document_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
}
