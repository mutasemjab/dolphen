<?php

namespace App\Models\Admin;

class StockMovement extends Record
{
    use AppendOnly;
    protected $table = 'erp_stock_movements';
    protected $casts = ['quantity' => 'decimal:6'];
    public function invoice() { return $this->belongsTo(Invoice::class, 'document_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
