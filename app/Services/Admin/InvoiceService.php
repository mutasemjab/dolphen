<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Admin\{Invoice, Product, Partner, StockMovement, Warehouse};
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
            $invoice->update(['status' => 'cancelled', 'metadata' => ($invoice->metadata ?? []) + ['cancel_reason' => $reason, 'cancelled_on' => now()->toDateString()]]);
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
        D::check(Warehouse::whereKey($data['warehouse_id'])->where('active', true)->exists(), 'inactive_record');
        return Invoice::create(['number' => Context::number($type, $data['date']), 'type' => $type, 'status' => $status,
            'date' => $data['date'], 'partner_id' => $data['partner_id'] ?? null, 'warehouse_id' => $data['warehouse_id'],
            'currency' => Context::setting('currency', 'JOD'), 'notes' => $data['notes'] ?? null, 'created_by' => $actor->id,
            'source_id' => $source, 'metadata' => $metadata]);
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
