<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Invoice, Partner};
use App\Services\Admin\{Context, InvoiceService};
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;

/**
 * Customer and supplier account statements, built from posted and cancelled invoices and their payments.
 * The balance is what the customer owes us, or what we owe the supplier.
 */
class StatementController extends Controller
{
    private const KINDS = ['customers' => ['customer', 'sales'], 'suppliers' => ['supplier', 'purchasing']];

    public function show(Request $request, string $kind)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        [$partnerKind, $module] = self::KINDS[$kind];
        Context::authorize($request->user('admin'), $module.'.view');
        $request->validate(['partner_id' => 'nullable|integer', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $partners = Partner::whereIn('kind', [$partnerKind, 'both'])->orderBy('name_ar')->get();
        if (!$request->filled('partner_id')) {
            return view('admin.statements.index', ['kind' => $kind, 'partners' => $partners, 'balances' => $this->balances($partners->pluck('id')->all(), $kind)]);
        }
        $partner = $partners->firstWhere('id', $request->integer('partner_id')); abort_unless($partner, 404);
        $from = $request->input('from'); $to = $request->input('to');
        $opening = '0'; $rows = []; $debit = $credit = '0';
        foreach ($this->entries($partner->id) as $entry) {
            $change = $kind === 'customers' ? D::sub($entry['debit'], $entry['credit']) : D::sub($entry['credit'], $entry['debit']);
            if ($from && $entry['date'] < $from) { $opening = D::add($opening, $change); continue; }
            if ($to && $entry['date'] > $to) { continue; }
            $rows[] = $entry + ['change' => $change];
            $debit = D::add($debit, $entry['debit']); $credit = D::add($credit, $entry['credit']);
        }
        $balance = $opening;
        foreach ($rows as &$row) { $balance = D::add($balance, $row['change']); $row['balance'] = $balance; }
        unset($row);
        return view('admin.statements.show', compact('kind', 'partners', 'partner', 'rows', 'opening', 'debit', 'credit', 'balance', 'from', 'to'));
    }

    /** Every statement line of a partner in date order. Debit raises what the partner owes us; credit raises what we owe. */
    private function entries(int $partnerId): array
    {
        $entries = [];
        $invoices = Invoice::where('partner_id', $partnerId)->whereIn('type', array_keys(InvoiceService::TYPES))
            ->whereIn('status', ['posted', 'cancelled'])->orderBy('date')->orderBy('id')->get();
        foreach ($invoices as $invoice) {
            $sale = $invoice->type === 'sales_invoice';
            $add = function (string $date, string $description, string $amount, bool $invoiceSide, int $order) use (&$entries, $invoice, $sale) {
                if (D::cmp($amount) === 0) { return; }
                // An invoice amount is a debit for a sale and a credit for a purchase; payments are the opposite.
                $isDebit = $invoiceSide === $sale;
                $entries[] = ['date' => $date, 'order' => [$date, $invoice->id, $order], 'invoice' => $invoice, 'description' => $description,
                    'debit' => $isDebit ? D::of($amount) : '0.000000', 'credit' => $isDebit ? '0.000000' : D::of($amount)];
            };
            $date = $invoice->date->format('Y-m-d');
            $payments = $invoice->metadata['payments'] ?? [];
            $initial = D::sub($invoice->paid, D::sum(array_column($payments, 'amount')));
            $add($date, $invoice->type, (string) $invoice->total, true, 0);
            $add($date, $sale ? 'payment_received' : 'payment_made', $initial, false, 1);
            foreach ($payments as $payment) { $add($payment['date'], $sale ? 'payment_received' : 'payment_made', (string) $payment['amount'], false, 1); }
            if ($invoice->status === 'cancelled') {
                $cancelled = $invoice->metadata['cancelled_on'] ?? $invoice->updated_at->format('Y-m-d');
                // Cancelling voids the invoice and settles what was paid on it.
                $add($cancelled, 'invoice_cancelled', (string) $invoice->total, false, 2);
                $add($cancelled, 'cancelled_payment_settled', (string) $invoice->paid, true, 2);
            }
        }
        usort($entries, fn ($a, $b) => $a['order'] <=> $b['order']);
        return $entries;
    }

    private function balances(array $partnerIds, string $kind): array
    {
        $rows = Invoice::whereIn('partner_id', $partnerIds)->whereIn('type', array_keys(InvoiceService::TYPES))->where('status', 'posted')
            ->get(['partner_id', 'type', 'total', 'paid']);
        $balances = [];
        foreach ($rows as $row) {
            $open = D::sub($row->total, $row->paid);
            $signed = ($row->type === 'sales_invoice') === ($kind === 'customers') ? $open : D::neg($open);
            $balances[$row->partner_id] = D::add($balances[$row->partner_id] ?? '0', $signed);
        }
        return $balances;
    }
}
