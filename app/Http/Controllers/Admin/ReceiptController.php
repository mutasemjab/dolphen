<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Currency, Invoice, Partner};
use App\Services\Admin\{Context, InvoiceService};
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;

/** Customer receipts (سندات القبض). */
class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        Context::authorize($request->user('admin'), 'sales.view');
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'partner_id' => 'nullable|integer', 'currency' => 'nullable|string|size:3', 'payment_method' => 'nullable|in:'.implode(',', InvoiceService::PAYMENT_METHODS)]);
        $query = Invoice::with('partner')->where('type', InvoiceService::RECEIPT)
            ->when($request->q, fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'))
            ->when($request->partner_id, fn ($q, $id) => $q->where('partner_id', $id))
            ->when($request->currency, fn ($q, $code) => $q->where('currency', $code))
            ->when($request->payment_method, fn ($q, $method) => $q->where('metadata->payment_method', $method))
            ->when($request->from, fn ($q, $date) => $q->where('date', '>=', $date))->when($request->to, fn ($q, $date) => $q->where('date', '<=', $date));
        // Totals per currency: amounts in different currencies are never added together.
        $totals = (clone $query)->where('status', 'posted')->get(['currency', 'total'])->groupBy('currency')->map(fn ($rows) => D::sum($rows->pluck('total')));
        return view('admin.receipts.index', ['receipts' => $query->latest('id')->paginate(20)->withQueryString(), 'totals' => $totals,
            'partners' => $this->customers(false), 'currencies' => Currency::orderBy('id')->get()]);
    }

    public function create(Request $request)
    {
        Context::authorize($request->user('admin'), 'sales.manage');
        $invoice = $request->filled('invoice')
            ? Invoice::where('type', 'sales_invoice')->where('status', 'posted')->findOrFail($request->integer('invoice')) : null;
        $open = Invoice::where('type', 'sales_invoice')->where('status', 'posted')->whereColumn('paid', '<', 'total')->orderBy('date')->orderBy('id')->get();
        return view('admin.receipts.form', ['invoice' => $invoice, 'open' => $open, 'partners' => $this->customers(),
            'currencies' => Currency::where('active', true)->orderBy('id')->get()]);
    }

    public function store(Request $request, InvoiceService $service)
    {
        $receipt = $service->saveReceipt($request->user('admin'), $request->all());
        return redirect()->route('admin.receipts.show', $receipt)->with('success', __('erp.saved'));
    }

    public function show(Request $request, Invoice $receipt)
    {
        abort_unless($receipt->type === InvoiceService::RECEIPT, 404);
        Context::authorize($request->user('admin'), 'sales.view');
        return view('admin.receipts.show', ['receipt' => $receipt->load('partner')]);
    }

    public function apply(Request $request, Invoice $receipt, InvoiceService $service)
    {
        $service->applyReceipt($request->user('admin'), $receipt);
        return back()->with('success', __('erp.saved'));
    }

    public function cancel(Request $request, Invoice $receipt, InvoiceService $service)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $service->cancelReceipt($request->user('admin'), $receipt, $data['reason']);
        return back()->with('success', __('erp.cancelled'));
    }

    private function customers(bool $activeOnly = true)
    {
        return Partner::when($activeOnly, fn ($q) => $q->where('active', true))->whereIn('kind', ['customer', 'both'])->orderBy('name_ar')->get();
    }
}
