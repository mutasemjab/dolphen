<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Invoice, Partner, Product, Warehouse};
use App\Services\Admin\{Context, InvoiceService};
use Illuminate\Http\Request;

/** Goods receipt (stock in) and goods issue (stock out) vouchers. */
class VoucherController extends Controller
{
    private function type(string $type): void { abort_unless(isset(InvoiceService::VOUCHERS[$type]), 404); }

    public function index(Request $request, string $type)
    {
        $this->type($type); Context::authorize($request->user('admin'), 'inventory.view');
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'warehouse_id' => 'nullable|integer', 'origin' => 'nullable|in:manual,invoice']);
        $vouchers = Invoice::with('warehouse', 'partner', 'source')->withCount('lines')->where('type', $type)
            ->when($request->q, fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'))
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->origin === 'manual', fn ($q) => $q->whereNull('source_id'))
            ->when($request->origin === 'invoice', fn ($q) => $q->whereNotNull('source_id'))
            ->when($request->from, fn ($q, $date) => $q->where('date', '>=', $date))->when($request->to, fn ($q, $date) => $q->where('date', '<=', $date))
            ->latest('id')->paginate(20)->withQueryString();
        return view('admin.vouchers.index', ['type' => $type, 'vouchers' => $vouchers, 'warehouses' => Warehouse::orderBy('id')->get()]);
    }

    public function create(Request $request, string $type)
    {
        $this->type($type); Context::authorize($request->user('admin'), 'inventory.manage');
        return view('admin.vouchers.form', ['type' => $type, 'warehouses' => Warehouse::where('active', true)->get(),
            'partners' => Partner::where('active', true)->orderBy('name_ar')->get(),
            'products' => Product::with('baseUnit')->where('active', true)->orderBy('name_ar')->get()]);
    }

    public function store(Request $request, string $type, InvoiceService $service)
    {
        $this->type($type);
        $voucher = $service->saveVoucher($request->user('admin'), $type, $request->all());
        return redirect()->route('admin.vouchers.show', $voucher)->with('success', __('erp.saved'));
    }

    public function show(Request $request, Invoice $voucher)
    {
        $this->type($voucher->type); Context::authorize($request->user('admin'), 'inventory.view');
        return view('admin.vouchers.show', ['voucher' => $voucher->load('lines.product', 'lines.unit', 'partner', 'warehouse', 'source')]);
    }

    public function cancel(Request $request, Invoice $voucher, InvoiceService $service)
    {
        $this->type($voucher->type);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $service->cancelVoucher($request->user('admin'), $voucher, $data['reason']);
        return back()->with('success', __('erp.cancelled'));
    }
}
