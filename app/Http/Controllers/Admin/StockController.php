<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Product, Warehouse, StockMovement};
use App\Services\Admin\{Context, InvoiceService};
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        Context::authorize($request->user('admin'), 'inventory.view');
        $request->validate(['warehouse_id' => 'nullable|exists:erp_warehouses,id', 'q' => 'nullable|string|max:100']);
        $warehouses = Warehouse::where('active', true)->get();
        $warehouseId = $request->integer('warehouse_id') ?: $warehouses->first()?->id;
        $products = Product::with('baseUnit')->select('erp_products.*')
            ->selectSub(StockMovement::selectRaw('COALESCE(SUM(quantity),0)')->whereColumn('product_id', 'erp_products.id')->where('warehouse_id', $warehouseId), 'quantity')
            // Products assigned to this warehouse, unassigned ones, and any product that has moved through it.
            ->where(fn ($q) => $q->where('warehouse_id', $warehouseId)->orWhereNull('warehouse_id')
                ->orWhereExists(fn ($s) => $s->from('erp_stock_movements')->whereColumn('product_id', 'erp_products.id')->where('warehouse_id', $warehouseId)))
            ->when($request->q, fn ($q, $term) => $q->where('name_ar', 'like', '%'.$term.'%'))
            ->orderBy('name_ar')->paginate(20)->withQueryString();
        $movements = StockMovement::with('product', 'invoice')->where('warehouse_id', $warehouseId)->latest('id')->limit(20)->get();
        $choices = Product::where('active', true)->orderBy('name_ar')->get();
        return view('admin.stock', compact('warehouses', 'warehouseId', 'products', 'movements', 'choices'));
    }

    public function adjust(Request $request, InvoiceService $service)
    {
        $service->adjust($request->user('admin'), $request->all());
        return back()->with('success', __('erp.saved'));
    }
}
