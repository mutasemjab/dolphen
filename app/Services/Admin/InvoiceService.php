<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Admin\{Currency, Invoice, Product, Partner, StockMovement, Warehouse};
use App\Support\Admin\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceService
{
    /** Posted invoices: they change stock (through their voucher) and partner balances. */
    public const TYPES = ['sales_invoice' => 'sales', 'purchase_invoice' => 'purchasing'];
    /** Every invoice screen, including the pro forma that changes nothing until it is converted. */
    public const FORMS = self::TYPES + ['proforma_invoice' => 'sales'];
    /** Stock vouchers and the direction in which they move stock. */
    public const VOUCHERS = ['receipt_voucher' => 1, 'issue_voucher' => -1];
    /** The voucher an invoice posts automatically. */
    public const VOUCHER_FOR = ['purchase_invoice' => 'receipt_voucher', 'sales_invoice' => 'issue_voucher'];

    /** Customer receipt (سند قبض): money received, applied to open sales invoices. */
    public const RECEIPT = 'cash_receipt';
    public const PAYMENT_METHODS = ['cash', 'cheque', 'bank_transfer'];

    public static function sells(string $type): bool { return in_array($type, ['sales_invoice', 'proforma_invoice'], true); }

    public function quantity(int $product, int $warehouse): string
    {
        return StockMovement::where('product_id', $product)->where('warehouse_id', $warehouse)
            ->selectRaw('COALESCE(SUM(quantity),0) as quantity')->first()->quantity;
    }

    public function save(Admin $actor, string $type, array $data): Invoice
    {
        abort_unless(isset(self::FORMS[$type]), 404);
        Context::authorize($actor, self::FORMS[$type].'.manage');
        Validator::make($data, [
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'partner_id' => 'required|exists:erp_partners,id', 'warehouse_id' => 'required|exists:erp_warehouses,id',
            'request_id' => 'required|uuid', 'notes' => 'nullable|string|max:2000',
            'valid_until' => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'proforma_id' => 'nullable|integer|exists:erp_documents,id',
            'currency' => 'nullable|string|size:3', 'exchange_rate' => 'nullable|numeric|gt:0',
            'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|exists:erp_products,id',
            'lines.*.quantity' => 'required|numeric|gt:0', 'lines.*.unit_price' => 'required|numeric|min:0',
        ])->validate();
        $proforma = $type === 'proforma_invoice';

        return DB::transaction(function () use ($actor, $type, $data, $proforma) {
            Context::lock();
            if ($existing = $this->repeat($actor, $type, $data['request_id'])) { return $existing; }
            $partner = Partner::findOrFail($data['partner_id']);
            D::check($partner->active && in_array($partner->kind, [self::sells($type) ? 'customer' : 'supplier', 'both']), 'partner_required');
            $source = null;
            if ($type === 'sales_invoice' && !empty($data['proforma_id'])) {
                // Locked so that one pro forma can never become two sales invoices.
                $source = Invoice::where('type', 'proforma_invoice')->lockForUpdate()->find($data['proforma_id']);
                D::check($source && $source->status === 'open', 'proforma_not_open');
            }
            $invoice = $this->header($actor, $type, $data, $proforma ? 'open' : 'posted', ['request_id' => $data['request_id']], $source?->id);
            $voucher = isset(self::VOUCHER_FOR[$type])
                ? $this->header($actor, self::VOUCHER_FOR[$type], $data, 'posted', ['source_number' => $invoice->number], $invoice->id) : null;
            $subtotal = '0';
            foreach ($data['lines'] as $input) {
                $product = Product::findOrFail($input['product_id']);
                D::check((bool) $product->active, 'inactive_record');
                D::check(!self::sells($type) || $product->item_type !== 'raw', 'raw_not_sellable', ['product' => $product->label]);
                $quantity = D::of($input['quantity']); $price = D::of($input['unit_price']);
                D::check(D::cmp($quantity) > 0 && D::cmp($price) >= 0, 'positive_quantity');
                $net = D::mul($quantity, $price); D::of($net);
                $invoice->lines()->create(['product_id' => $product->id, 'unit_id' => $product->base_unit_id,
                    'quantity' => $quantity, 'base_quantity' => $quantity, 'unit_price' => $price, 'net' => $net, 'total' => $net]);
                if ($voucher) { $this->voucherLine($voucher, $product, $quantity); }
                $subtotal = D::add($subtotal, $net);
            }
            $discount = D::of($data['discount'] ?? '0'); $rate = D::of($data['tax_rate'] ?? '0');
            D::check(D::cmp($discount) >= 0 && D::cmp($discount, $subtotal) <= 0, 'invalid_discount');
            D::check(D::cmp($rate) >= 0 && D::cmp($rate, '100') <= 0, 'invalid_tax');
            $tax = D::div(D::mul(D::sub($subtotal, $discount), $rate), '100');
            $total = D::add(D::sub($subtotal, $discount), $tax); D::of($total);
            $paid = $proforma ? '0' : D::of($data['paid'] ?? '0');
            D::check(D::cmp($paid) >= 0 && D::cmp($paid, $total) <= 0, 'overpayment');
            $invoice->update(['subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $total, 'paid' => $paid,
                'metadata' => $invoice->metadata + ['discount' => $discount, 'tax_rate' => $rate]
                    + ($proforma && !empty($data['valid_until']) ? ['valid_until' => $data['valid_until']] : [])]);
            if ($source) {
                $source->update(['status' => 'converted', 'metadata' => ($source->metadata ?? []) + ['converted_to' => $invoice->id, 'converted_number' => $invoice->number]]);
            }
            return $invoice->fresh('lines');
        }, 3);
    }

    public function pay(Admin $actor, Invoice $invoice, string $amount, string $requestId): void
    {
        abort_unless(isset(self::TYPES[$invoice->type]), 404);
        Context::authorize($actor, self::TYPES[$invoice->type].'.manage');
        Validator::make(['request_id' => $requestId], ['request_id' => 'required|uuid'])->validate();
        DB::transaction(function () use ($invoice, $amount, $requestId) {
            Context::lock(); $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            D::check($invoice->status === 'posted', 'invalid_status');
            $metadata = $invoice->metadata ?? [];
            if (isset($metadata['payments'][$requestId])) { return; }
            $amount = D::of($amount);
            D::check(D::cmp($amount) > 0 && D::cmp($amount, D::sub($invoice->total, $invoice->paid)) <= 0, 'overpayment');
            $metadata['payments'][$requestId] = ['amount' => $amount, 'date' => now()->toDateString()];
            $invoice->update(['paid' => D::add($invoice->paid, $amount), 'metadata' => $metadata]);
        }, 3);
    }

    public function cancel(Admin $actor, Invoice $invoice, string $reason): void
    {
        abort_unless(isset(self::FORMS[$invoice->type]), 404);
        Context::authorize($actor, self::FORMS[$invoice->type].'.manage');
        D::check(trim($reason) !== '', 'reason_required');
        DB::transaction(function () use ($invoice, $reason) {
            Context::lock(); $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === 'cancelled') { return; }
            D::check($invoice->status === ($invoice->type === 'proforma_invoice' ? 'open' : 'posted'), 'invalid_status');
            // Invoices saved before vouchers existed own their movements; newer ones move stock through their voucher.
            $vouchers = Invoice::where('source_id', $invoice->id)->whereIn('type', array_keys(self::VOUCHERS))->lockForUpdate()->get();
            $this->reverse($invoice->id, ...$vouchers->pluck('id')->map('intval')->all());
            $vouchers->each(fn ($voucher) => $voucher->update(['status' => 'cancelled', 'metadata' => ($voucher->metadata ?? []) + ['cancel_reason' => $reason, 'cancelled_on' => now()->toDateString()]]));
            // Money from receipts stays with the customer as credit instead of being treated as refunded.
            $metadata = $invoice->metadata ?? []; $paid = (string) $invoice->paid;
            foreach ($metadata['payments'] ?? [] as $key => $payment) {
                if (empty($payment['receipt_id'])) { continue; }
                $this->release($invoice, $payment);
                $paid = D::sub($paid, $payment['amount']); unset($metadata['payments'][$key]);
            }
            $invoice->update(['status' => 'cancelled', 'paid' => $paid, 'metadata' => $metadata + ['cancel_reason' => $reason, 'cancelled_on' => now()->toDateString()]]);
        }, 3);
    }

    /** A customer receipt (سند قبض). Applied to open invoices in its currency; any excess stays as credit. */
    public function saveReceipt(Admin $actor, array $data): Invoice
    {
        Context::authorize($actor, 'sales.manage');
        Validator::make($data, [
            'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'partner_id' => 'required|exists:erp_partners,id',
            'currency' => 'nullable|string|size:3', 'exchange_rate' => 'nullable|numeric|gt:0', 'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:'.implode(',', self::PAYMENT_METHODS),
            'cheque_number' => 'nullable|required_if:payment_method,cheque|string|max:100', 'cheque_date' => 'nullable|required_if:payment_method,cheque|date_format:Y-m-d',
            'bank_name' => 'nullable|string|max:200', 'invoice_id' => 'nullable|integer', 'request_id' => 'required|uuid', 'notes' => 'nullable|string|max:2000',
        ])->validate();
        return DB::transaction(function () use ($actor, $data) {
            Context::lock();
            if ($existing = $this->repeat($actor, self::RECEIPT, $data['request_id'])) { return $existing; }
            $partner = Partner::findOrFail($data['partner_id']);
            D::check($partner->active && in_array($partner->kind, ['customer', 'both']), 'partner_required');
            $amount = D::of((string) $data['amount']); D::check(D::cmp($amount) > 0, 'positive_quantity');
            $method = $data['payment_method'];
            $receipt = $this->header($actor, self::RECEIPT, array_replace($data, ['warehouse_id' => null]), 'posted', ['request_id' => $data['request_id'], 'payment_method' => $method]
                + ($method === 'cash' ? [] : array_filter(['cheque_number' => $data['cheque_number'] ?? null, 'cheque_date' => $method === 'cheque' ? ($data['cheque_date'] ?? null) : null, 'bank_name' => $data['bank_name'] ?? null])));
            $receipt->update(['subtotal' => $amount, 'total' => $amount, 'paid' => '0']);
            $this->recordAllocations($receipt, $this->allocate($receipt, $amount, !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null));
            return $receipt->fresh();
        }, 3);
    }

    /** Applies a receipt's unallocated credit to invoices issued after it. */
    public function applyReceipt(Admin $actor, Invoice $receipt): void
    {
        abort_unless($receipt->type === self::RECEIPT, 404);
        Context::authorize($actor, 'sales.manage');
        DB::transaction(function () use ($receipt) {
            Context::lock(); $receipt = Invoice::lockForUpdate()->findOrFail($receipt->id);
            D::check($receipt->status === 'posted', 'invalid_status');
            $credit = D::sub($receipt->total, $receipt->paid);
            if (D::cmp($credit) <= 0) { return; }
            $this->recordAllocations($receipt, $this->allocate($receipt, $credit));
        }, 3);
    }

    public function cancelReceipt(Admin $actor, Invoice $receipt, string $reason): void
    {
        abort_unless($receipt->type === self::RECEIPT, 404);
        Context::authorize($actor, 'sales.manage');
        D::check(trim($reason) !== '', 'reason_required');
        DB::transaction(function () use ($receipt, $reason) {
            Context::lock(); $receipt = Invoice::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status === 'cancelled') { return; }
            D::check($receipt->status === 'posted', 'invalid_status');
            $key = 'receipt:'.$receipt->id;
            foreach ($receipt->metadata['allocations'] ?? [] as $allocation) {
                $invoice = Invoice::lockForUpdate()->find($allocation['invoice_id']);
                $payment = $invoice?->metadata['payments'][$key] ?? null;
                if (!$payment) { continue; }
                $metadata = $invoice->metadata; unset($metadata['payments'][$key]);
                $invoice->update(['paid' => D::sub($invoice->paid, $payment['amount']), 'metadata' => $metadata]);
            }
            $receipt->update(['status' => 'cancelled', 'paid' => '0', 'metadata' => ($receipt->metadata ?? []) + ['cancel_reason' => $reason, 'cancelled_on' => now()->toDateString()]]);
        }, 3);
    }

    /** A manual goods receipt or goods issue voucher. */
    public function saveVoucher(Admin $actor, string $type, array $data): Invoice
    {
        abort_unless(isset(self::VOUCHERS[$type]), 404);
        Context::authorize($actor, 'inventory.manage');
        Validator::make($data, [
            'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'warehouse_id' => 'required|exists:erp_warehouses,id',
            'partner_id' => 'nullable|exists:erp_partners,id', 'request_id' => 'required|uuid', 'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|exists:erp_products,id',
            'lines.*.quantity' => 'required|numeric|gt:0',
        ])->validate();
        return DB::transaction(function () use ($actor, $type, $data) {
            Context::lock();
            if ($existing = $this->repeat($actor, $type, $data['request_id'])) { return $existing; }
            D::check(empty($data['partner_id']) || Partner::whereKey($data['partner_id'])->where('active', true)->exists(), 'inactive_record');
            $voucher = $this->header($actor, $type, $data, 'posted', ['request_id' => $data['request_id']]);
            foreach ($data['lines'] as $input) {
                $product = Product::findOrFail($input['product_id']);
                D::check((bool) $product->active, 'inactive_record');
                $quantity = D::of($input['quantity']); D::check(D::cmp($quantity) > 0, 'positive_quantity');
                $this->voucherLine($voucher, $product, $quantity);
            }
            return $voucher->fresh('lines');
        }, 3);
    }

    public function cancelVoucher(Admin $actor, Invoice $voucher, string $reason): void
    {
        abort_unless(isset(self::VOUCHERS[$voucher->type]), 404);
        Context::authorize($actor, 'inventory.manage');
        D::check(trim($reason) !== '', 'reason_required');
        DB::transaction(function () use ($voucher, $reason) {
            Context::lock(); $voucher = Invoice::lockForUpdate()->findOrFail($voucher->id);
            if ($voucher->status === 'cancelled') { return; }
            D::check($voucher->status === 'posted', 'invalid_status');
            D::check(!$voucher->source_id, 'voucher_from_invoice');
            $this->reverse($voucher->id);
            $voucher->update(['status' => 'cancelled', 'metadata' => ($voucher->metadata ?? []) + ['cancel_reason' => $reason, 'cancelled_on' => now()->toDateString()]]);
        }, 3);
    }

    public function adjust(Admin $actor, array $data): void
    {
        Context::authorize($actor, 'inventory.manage');
        Validator::make($data, ['product_id' => 'required|exists:erp_products,id', 'warehouse_id' => 'required|exists:erp_warehouses,id',
            'quantity' => 'required|numeric|not_in:0', 'notes' => 'required|string|max:2000', 'request_id' => 'required|uuid'])->validate();
        DB::transaction(function () use ($actor, $data) {
            Context::lock();
            if ($this->repeat($actor, 'adjustment', $data['request_id'])) { return; }
            $product = Product::findOrFail($data['product_id']);
            D::check((bool) $product->active, 'inactive_record');
            $invoice = $this->header($actor, 'adjustment', $data + ['date' => now()->toDateString()], 'posted', ['request_id' => $data['request_id']]);
            $quantity = D::of($data['quantity']); D::check(D::cmp($quantity) !== 0, 'positive_quantity');
            $this->move($invoice, $product, $quantity, 'adjustment');
        }, 3);
    }

    private function repeat(Admin $actor, string $type, string $requestId): ?Invoice
    {
        return Invoice::where('created_by', $actor->id)->where('type', $type)->where('metadata->request_id', $requestId)->first();
    }

    private function header(Admin $actor, string $type, array $data, string $status, array $metadata, ?int $source = null): Invoice
    {
        D::check(empty($data['warehouse_id']) ? $type === self::RECEIPT : Warehouse::whereKey($data['warehouse_id'])->where('active', true)->exists(), 'inactive_record');
        [$currency, $rate] = $this->currency($data);
        return Invoice::create(['number' => Context::number($type, $data['date']), 'type' => $type, 'status' => $status,
            'date' => $data['date'], 'partner_id' => $data['partner_id'] ?? null, 'warehouse_id' => $data['warehouse_id'] ?? null,
            'currency' => $currency, 'exchange_rate' => $rate, 'notes' => $data['notes'] ?? null, 'created_by' => $actor->id,
            'source_id' => $source, 'metadata' => $metadata]);
    }

    /** The document currency and its rate to the base currency. The base currency is always 1; others default to the saved rate. */
    private function currency(array $data): array
    {
        $code = strtoupper(trim((string) ($data['currency'] ?? ''))) ?: Currency::base();
        $currency = Currency::where('code', $code)->first();
        D::check($currency && $currency->active, 'invalid_currency');
        if ($code === Currency::base()) { return [$code, '1.000000']; }
        $rate = isset($data['exchange_rate']) && $data['exchange_rate'] !== '' ? D::of((string) $data['exchange_rate']) : D::of($currency->exchange_rate);
        D::check(D::cmp($rate) > 0, 'invalid_exchange_rate');
        return [$code, $rate];
    }

    /** Applies up to $amount of a receipt to the customer's open invoices in its currency: the chosen invoice first, then the oldest. */
    private function allocate(Invoice $receipt, string $amount, ?int $invoiceId = null): array
    {
        $open = Invoice::where('type', 'sales_invoice')->where('status', 'posted')->where('partner_id', $receipt->partner_id)
            ->where('currency', $receipt->currency)->whereColumn('paid', '<', 'total')->orderBy('date')->orderBy('id')->lockForUpdate()->get();
        if ($invoiceId) {
            $chosen = $open->firstWhere('id', $invoiceId);
            D::check((bool) $chosen, 'receipt_invoice_mismatch');
            $open = collect([$chosen])->merge($open->reject(fn ($invoice) => $invoice->id === $chosen->id));
        }
        $allocations = []; $remaining = $amount; $key = 'receipt:'.$receipt->id;
        foreach ($open as $invoice) {
            if (D::cmp($remaining) <= 0) { break; }
            $due = D::sub($invoice->total, $invoice->paid);
            $take = D::cmp($remaining, $due) >= 0 ? $due : $remaining;
            $metadata = $invoice->metadata ?? [];
            $previous = $metadata['payments'][$key]['amount'] ?? '0';
            $metadata['payments'][$key] = ['amount' => D::add($previous, $take), 'date' => $metadata['payments'][$key]['date'] ?? $receipt->date->format('Y-m-d'),
                'receipt_id' => $receipt->id, 'receipt_number' => $receipt->number];
            $invoice->update(['paid' => D::add($invoice->paid, $take), 'metadata' => $metadata]);
            $allocations[] = ['invoice_id' => $invoice->id, 'number' => $invoice->number, 'amount' => $take];
            $remaining = D::sub($remaining, $take);
        }
        return $allocations;
    }

    /** Merges new allocations into the receipt's list (one row per invoice). */
    private function recordAllocations(Invoice $receipt, array $allocations): void
    {
        $metadata = $receipt->metadata ?? []; $rows = collect($metadata['allocations'] ?? [])->keyBy('invoice_id');
        foreach ($allocations as $allocation) {
            $row = $rows->get($allocation['invoice_id']);
            $rows->put($allocation['invoice_id'], $row ? array_replace($row, ['amount' => D::add($row['amount'], $allocation['amount'])]) : $allocation);
        }
        $metadata['allocations'] = $rows->values()->all();
        $receipt->update(['paid' => D::add($receipt->paid, D::sum(array_column($allocations, 'amount'))), 'metadata' => $metadata]);
    }

    /** Detaches a receipt's payment from an invoice that is being cancelled; the amount stays on the customer's account as credit. */
    private function release(Invoice $invoice, array $payment): void
    {
        $receipt = Invoice::where('type', self::RECEIPT)->lockForUpdate()->find($payment['receipt_id']);
        if (!$receipt) { return; }
        $metadata = $receipt->metadata ?? [];
        $metadata['allocations'] = array_values(array_filter($metadata['allocations'] ?? [], fn ($row) => (int) $row['invoice_id'] !== $invoice->id));
        $metadata['released'][] = ['invoice_id' => $invoice->id, 'number' => $invoice->number, 'amount' => $payment['amount'], 'date' => now()->toDateString()];
        $receipt->update(['paid' => D::sub($receipt->paid, $payment['amount']), 'metadata' => $metadata]);
    }

    private function voucherLine(Invoice $voucher, Product $product, string $quantity): void
    {
        $line = $voucher->lines()->create(['product_id' => $product->id, 'unit_id' => $product->base_unit_id,
            'quantity' => $quantity, 'base_quantity' => $quantity, 'unit_price' => '0', 'net' => '0', 'total' => '0']);
        $this->move($voucher, $product, self::VOUCHERS[$voucher->type] > 0 ? $quantity : D::neg($quantity), 'line:'.$line->id, $line->id);
    }

    /** Posts the opposite of every movement of the given documents, each under its own document. */
    private function reverse(int ...$documents): void
    {
        $movements = StockMovement::with('product', 'invoice')->whereIn('document_id', $documents)->where('kind', '!=', 'cancelled')->orderBy('id')->get();
        foreach ($movements as $movement) {
            $this->move($movement->invoice, $movement->product, D::neg($movement->quantity), 'cancel:'.$movement->id, null, now()->toDateString());
        }
    }

    private function move(Invoice $invoice, Product $product, string $quantity, string $event, ?int $line = null, ?string $date = null): void
    {
        $date ??= $invoice->date->format('Y-m-d');
        $lastDate = StockMovement::where('product_id', $product->id)->where('warehouse_id', $invoice->warehouse_id)->max('date');
        D::check(!$lastDate || $date >= $lastDate, 'backdated_stock');
        $available = $this->quantity($product->id, $invoice->warehouse_id);
        D::check(D::cmp(D::add($available, $quantity)) >= 0, 'shortage', ['product' => $product->label, 'available' => $available]);
        StockMovement::create(['document_id' => $invoice->id, 'line_id' => $line, 'product_id' => $product->id,
            'warehouse_id' => $invoice->warehouse_id, 'unit_id' => $product->base_unit_id, 'date' => $date,
            'kind' => str_starts_with($event, 'cancel:') ? 'cancelled' : $invoice->type,
            'quantity' => $quantity, 'entered_quantity' => $quantity, 'value' => '0', 'unit_cost' => '0',
            'event_key' => $invoice->id.':'.$event]);
    }
}
