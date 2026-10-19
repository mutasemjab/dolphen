<?php

namespace App\Models\Admin;

class Invoice extends Record
{
    protected $table = 'erp_documents';
    protected $casts = ['date' => \App\Support\Admin\CalendarDate::class, 'metadata' => 'array', 'subtotal' => 'decimal:6', 'tax_total' => 'decimal:6', 'total' => 'decimal:6', 'paid' => 'decimal:6'];
    public function lines() { return $this->hasMany(InvoiceLine::class, 'document_id'); }
    public function partner() { return $this->belongsTo(Partner::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function movements() { return $this->hasMany(StockMovement::class, 'document_id'); }
}
