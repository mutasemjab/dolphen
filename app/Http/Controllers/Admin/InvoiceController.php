<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Invoice, Partner, Product, Warehouse};
use App\Services\Admin\{Context, InvoiceService};
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    private function access(Request $request, string $type, string $action = 'view'): void
    {
        abort_unless(isset(InvoiceService::FORMS[$type]), 404);
        Context::authorize($request->user('admin'), InvoiceService::FORMS[$type].'.'.$action);
    }

    public function index(Request $request, string $type)
    {
        $this->access($request, $type);
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $query = Invoice::with('partner', 'warehouse')->where('type', $type)
            ->when($request->q, fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'))
            ->when($request->partner_id, fn ($q, $id) => $q->where('partner_id', $id))
            ->when($request->from, fn ($q, $date) => $q->where('date', '>=', $date))->when($request->to, fn ($q, $date) => $q->where('date', '<=', $date));
        $counted = (clone $query)->where('status', $type === 'proforma_invoice' ? 'open' : 'posted')->get();
        return view('admin.invoices.index', ['type' => $type, 'invoices' => $query->latest('id')->paginate(20)->withQueryString(),
            'partners' => $this->partners($type, false),
            'total' => D::sum($counted->pluck('total')), 'remaining' => D::sub(D::sum($counted->pluck('total')), D::sum($counted->pluck('paid')))]);
    }

    public function create(Request $request, string $type)
    {
        $this->access($request, $type, 'manage');
        $proforma = null;
        if ($type === 'sales_invoice' && $request->filled('proforma')) {
            $proforma = Invoice::with('lines')->where('type', 'proforma_invoice')->findOrFail($request->integer('proforma'));
            if ($proforma->status !== 'open') { return redirect()->route('admin.invoices.show', $proforma)->withErrors(['document' => __('erp.proforma_not_open')]); }
        }
        return view('admin.invoices.form', ['type' => $type, 'proforma' => $proforma, 'partners' => $this->partners($type),
            'warehouses' => Warehouse::where('active', true)->get(),
            'products' => Product::with('baseUnit')->where('active', true)->when(InvoiceService::sells($type), fn ($q) => $q->where('item_type', '!=', 'raw'))->orderBy('name_ar')->get()]);
    }

    public function store(Request $request, string $type, InvoiceService $service)
    {
        $this->access($request, $type, 'manage');
        $invoice = $service->save($request->user('admin'), $type, $request->all());
        return redirect()->route('admin.invoices.show', $invoice)->with('success', __('erp.saved'));
    }

    public function show(Request $request, Invoice $invoice)
    {
        if ($invoice->isVoucher()) { return redirect()->route('admin.vouchers.show', $invoice); }
        $this->access($request, $invoice->type);
        return view('admin.invoices.show', ['invoice' => $invoice->load('lines.product', 'lines.unit', 'partner', 'warehouse', 'source', 'derived')]);
    }

    public function pay(Request $request, Invoice $invoice, InvoiceService $service)
    {
        $this->access($request, $invoice->type, 'manage');
        $data = $request->validate(['amount' => 'required|string', 'request_id' => 'required|uuid']);
        $service->pay($request->user('admin'), $invoice, $data['amount'], $data['request_id']);
        return back()->with('success', __('erp.saved'));
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $service)
    {
        $this->access($request, $invoice->type, 'manage');
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $service->cancel($request->user('admin'), $invoice, $data['reason']);
        return back()->with('success', __('erp.cancelled'));
    }

    private function partners(string $type, bool $activeOnly = true)
    {
        return Partner::when($activeOnly, fn ($q) => $q->where('active', true))
            ->whereIn('kind', [InvoiceService::sells($type) ? 'customer' : 'supplier', 'both'])->orderBy('name_ar')->get();
    }
}
