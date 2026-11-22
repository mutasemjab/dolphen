@extends('admin.layouts.factory')
@section('title', __('erp.cash_receipt').' · '.$receipt->number)
@php($money = fn($v) => \App\Support\Admin\Format::money($v, $receipt->currency).' '.$receipt->currency)
@php($credit = \App\Support\Admin\Decimal::sub($receipt->total, $receipt->paid))
@php($method = $receipt->metadata['payment_method'] ?? 'cash')
@section('actions')<button class="btn" type="button" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('admin.receipts.index') }}">{{ __('erp.back') }}</a>
@endsection
@section('content')
<section class="panel form-panel">
    <div class="document-meta">
        <div><span>{{ __('erp.receipt_number') }}</span><strong>{{ $receipt->number }}</strong></div>
        <div><span>{{ __('erp.date') }}</span><strong>{{ $receipt->date->format('Y-m-d') }}</strong></div>
        <div><span>{{ __('erp.received_from') }}</span><strong>{{ $receipt->partner?->label }}</strong></div>
        <div><span>{{ __('erp.payment_method') }}</span><strong>{{ __('erp.'.$method) }}</strong></div>
        @foreach(['cheque_number','cheque_date','bank_name'] as $field)
        @if(!empty($receipt->metadata[$field]))<div><span>{{ __('erp.'.$field) }}</span><strong>{{ $receipt->metadata[$field] }}</strong></div>@endif
        @endforeach
        <div><span>{{ __('erp.status') }}</span><strong>{{ __('erp.'.$receipt->status) }}</strong></div>
    </div>
    <div class="receipt-amount"><span>{{ __('erp.amount_received') }}</span><strong>{{ $money($receipt->total) }}</strong>
        @if($receipt->currency !== \App\Models\Admin\Currency::base())<small>{{ __('erp.exchange_rate') }} {{ rtrim(rtrim($receipt->exchange_rate, '0'), '.') }} · {{ __('erp.total_in_base', ['base' => \App\Models\Admin\Currency::base()]) }} {{ \App\Support\Admin\Format::money($receipt->base($receipt->total)) }}</small>@endif
    </div>
    <h2>{{ __('erp.allocations') }}</h2>
    <div class="table-wrap"><table><thead><tr><th>{{ __('erp.number') }}</th><th>{{ __('erp.amount') }}</th></tr></thead><tbody>
    @forelse($receipt->metadata['allocations'] ?? [] as $allocation)
        <tr><td><a href="{{ route('admin.invoices.show', $allocation['invoice_id']) }}">{{ $allocation['number'] }}</a></td><td>{{ $money($allocation['amount']) }}</td></tr>
    @empty<tr><td class="empty" colspan="2">{{ __('erp.no_allocations') }}</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="document-totals">
        <div><span>{{ __('erp.allocated') }}</span><strong>{{ $money($receipt->paid) }}</strong></div>
        <div><span>{{ __('erp.unallocated') }}</span><strong>{{ $money($receipt->status === 'cancelled' ? '0' : $credit) }}</strong></div>
    </div>
    @foreach($receipt->metadata['released'] ?? [] as $released)
    <p class="hint">{{ __('erp.receipt_released', ['number' => $released['number'], 'amount' => $money($released['amount'])]) }}</p>
    @endforeach
    <p>{{ $receipt->notes }}</p>
    @if($receipt->status === 'cancelled')<p class="alert danger">{{ $receipt->metadata['cancel_reason'] ?? '' }}</p>@endif
    <div class="signatures"><div>{{ __('erp.receiver_signature') }}</div><div>{{ __('erp.accountant') }}</div></div>
</section>
@if($receipt->status === 'posted')
@can('erp.sales.manage')
<section class="panel form-panel no-print">
    @if(\App\Support\Admin\Decimal::cmp($credit) > 0)
    <form method="post" action="{{ route('admin.receipts.apply', $receipt) }}">@csrf
        <p class="hint">{{ __('erp.apply_credit_help') }}</p>
        <button class="btn primary">{{ __('erp.apply_credit') }}</button>
    </form>
    @endif
    <details class="mt-4"><summary>{{ __('erp.cancel_receipt') }}</summary>
        <p class="hint">{{ __('erp.cancel_receipt_help') }}</p>
        <form method="post" action="{{ route('admin.receipts.cancel', $receipt) }}" data-confirm="{{ __('erp.confirm_cancel_receipt') }}">@csrf
            @include('admin.field',['field'=>'reason','rule'=>'required'])
            <button class="btn danger-outline">{{ __('erp.cancel_receipt') }}</button>
        </form>
    </details>
</section>
@endcan
@endif
@endsection
