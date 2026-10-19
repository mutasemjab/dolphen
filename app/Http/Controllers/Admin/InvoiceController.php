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
        abort_unless(isset(InvoiceService::TYPES[$type]), 404);
        Context::authorize($request->user('admin'), InvoiceService::TYPES[$type].'.'.$action);
    }

    public function index(Request $request, string $type)
    {
        $this->access($request, $type);
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $query = Invoice::with('partner', 'warehouse')->where('type', $type)
            ->when($request->q, fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'))
            ->when($request->from, fn ($q, $date) => $q->where('date', '>=', $date))->when($request->to, fn ($q, $date) => $q->where('date', '<=', $date));
        $posted = (clone $query)->where('status', 'posted')->get();
        return view('admin.invoices.index', ['type' => $type, 'invoices' => $query->latest('id')->paginate(20)->withQueryString(),
            'total' => D::sum($posted->pluck('total')), 'remaining' => D::sub(D::sum($posted->pluck('total')), D::sum($posted->pluck('paid')))]);
    }

    public function create(Request $request, string $type)
    {
        $this->access($request, $type, 'manage');
        return view('admin.invoices.form', ['type' => $type, 'partners' => Partner::where('active', true)->whereIn('kind', [$type === 'sales_invoice' ? 'customer' : 'supplier', 'both'])->get(),
            'warehouses' => Warehouse::where('active', true)->get(), 'products' => Product::with('baseUnit')->where('active', true)->get()]);
    }

    public function store(Request $request, string $type, InvoiceService $service)
    {
        $this->access($request, $type, 'manage');
        $invoice = $service->save($request->user('admin'), $type, $request->all());
        return redirect()->route('admin.invoices.show', $invoice)->with('success', __('erp.saved'));
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->access($request, $invoice->type);
        return view('admin.invoices.show', ['invoice' => $invoice->load('lines.product', 'lines.unit', 'partner', 'warehouse')]);
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
}
