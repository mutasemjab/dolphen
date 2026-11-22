@extends('admin.layouts.factory')
@section('title', __('erp.'.$invoice->type).' · '.$invoice->number)
@php($isProforma = $invoice->type === 'proforma_invoice')
@php($module = \App\Services\Admin\InvoiceService::FORMS[$invoice->type])
@section('actions')
@if($isProforma && $invoice->status === 'open')
@can('erp.sales.manage')<a class="btn primary" href="{{ route('admin.invoices.create', ['sales_invoice', 'proforma' => $invoice->id]) }}">{{ __('erp.convert_to_sales_invoice') }}</a>@endcan
@endif
@if($invoice->type === 'sales_invoice' && $invoice->status === 'posted' && \App\Support\Admin\Decimal::cmp($invoice->total, $invoice->paid) > 0)
@can('erp.sales.manage')<a class="btn primary" href="{{ route('admin.receipts.create', ['invoice' => $invoice->id]) }}">+ {{ __('erp.cash_receipt') }}</a>@endcan
@endif
<button class="btn" type="button" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('admin.invoices.index',$invoice->type) }}">{{ __('erp.back') }}</a>
@endsection
@section('content')
<section class="panel form-panel">
    <div class="document-meta">
        <div><span>{{ __('erp.date') }}</span><strong>{{ $invoice->date->format('Y-m-d') }}</strong></div>
        <div><span>{{ __('erp.partner_id') }}</span><strong>{{ $invoice->partner?->label }}</strong></div>
        <div><span>{{ __('erp.warehouse_id') }}</span><strong>{{ $invoice->warehouse?->label }}</strong></div>
        <div><span>{{ __('erp.status') }}</span><strong>{{ __('erp.'.$invoice->status) }}</strong></div>
        <div><span>{{ __('erp.currency') }}</span><strong>{{ $invoice->currency }}@if($invoice->currency !== \App\Models\Admin\Currency::base()) · {{ __('erp.exchange_rate') }} {{ rtrim(rtrim($invoice->exchange_rate, '0'), '.') }}@endif</strong></div>
        @if(!empty($invoice->metadata['valid_until']))<div><span>{{ __('erp.valid_until') }}</span><strong>{{ $invoice->metadata['valid_until'] }}</strong></div>@endif
        @if($invoice->source)<div class="no-print"><span>{{ __('erp.from_proforma') }}</span><strong><a href="{{ $invoice->source->showRoute() }}">{{ $invoice->source->number }}</a></strong></div>@endif
        @foreach($invoice->derived as $derived)
        <div class="no-print"><span>{{ __('erp.'.$derived->type) }}</span><strong><a href="{{ $derived->showRoute() }}">{{ $derived->number }}</a></strong></div>
        @endforeach
    </div>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['product_id','quantity','base_unit_id','unit_price','total'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @foreach($invoice->lines as $line)
        <tr><td>{{ $line->product->label }}</td><td>{{ \App\Support\Admin\Format::number($line->quantity, 3) }}</td><td>{{ $line->unit->label }}</td><td>{{ \App\Support\Admin\Format::money($line->unit_price, $invoice->currency) }}</td><td>{{ \App\Support\Admin\Format::money($line->total, $invoice->currency) }}</td></tr>
    @endforeach
    </tbody></table></div>
    <div class="document-totals">
        @foreach(['subtotal'=>$invoice->subtotal,'discount'=>$invoice->metadata['discount'] ?? '0','tax_total'=>$invoice->tax_total,'total'=>$invoice->total] + ($isProforma ? [] : ['paid'=>$invoice->paid]) as $label=>$value)
        <div><span>{{ __('erp.'.$label) }}</span><strong>{{ \App\Support\Admin\Format::money($value, $invoice->currency) }} {{ $invoice->currency }}</strong></div>
        @endforeach
        @if($invoice->status === 'posted')
        <div><span>{{ __('erp.remaining') }}</span><strong>{{ \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($invoice->total,$invoice->paid), $invoice->currency) }} {{ $invoice->currency }}</strong></div>
        @endif
        @if($invoice->currency !== \App\Models\Admin\Currency::base())
        <div><span>{{ __('erp.total_in_base', ['base' => \App\Models\Admin\Currency::base()]) }}</span><strong>{{ \App\Support\Admin\Format::money($invoice->base($invoice->total)) }} {{ \App\Models\Admin\Currency::base() }}</strong></div>
        @endif
    </div>
    @if(!empty($invoice->metadata['payments']))
    <h2 class="no-print">{{ __('erp.payments') }}</h2>
    <div class="table-wrap no-print"><table><thead><tr><th>{{ __('erp.date') }}</th><th>{{ __('erp.amount') }}</th><th>{{ __('erp.cash_receipt') }}</th></tr></thead><tbody>
    @foreach($invoice->metadata['payments'] as $payment)
        <tr><td>{{ $payment['date'] }}</td><td>{{ \App\Support\Admin\Format::money($payment['amount'], $invoice->currency) }} {{ $invoice->currency }}</td>
        <td>@if(!empty($payment['receipt_id']))<a href="{{ route('admin.receipts.show', $payment['receipt_id']) }}">{{ $payment['receipt_number'] }}</a>@else—@endif</td></tr>
    @endforeach
    </tbody></table></div>
    @endif
    <p>{{ $invoice->notes }}</p>
    @if($invoice->status === 'cancelled')<p class="alert danger">{{ $invoice->metadata['cancel_reason'] ?? '' }}</p>
    @endif
</section>
@if(in_array($invoice->status, ['posted','open']))
@can('erp.'.$module.'.manage')
<section class="panel form-panel no-print">
    @if($invoice->status === 'posted')
    <h2>{{ __('erp.record_payment') }}</h2>
    <form method="post" action="{{ route('admin.invoices.pay',$invoice) }}">@csrf
        <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="form-grid">@include('admin.field',['field'=>'amount','rule'=>'required|numeric'])</div>
        <button class="btn primary">{{ __('erp.record_payment') }}</button>
    </form>
    @endif
    <details class="mt-4"><summary>{{ __($isProforma ? 'erp.cancel_proforma' : 'erp.cancel_invoice') }}</summary>
        <p class="hint">{{ __($isProforma ? 'erp.cancel_proforma_help' : 'erp.cancel_help') }}</p>
        <form method="post" action="{{ route('admin.invoices.cancel',$invoice) }}" data-confirm="{{ __($isProforma ? 'erp.confirm_cancel_proforma' : 'erp.confirm_cancel') }}">@csrf
            @include('admin.field',['field'=>'reason','rule'=>'required'])
            <button class="btn danger-outline">{{ __($isProforma ? 'erp.cancel_proforma' : 'erp.cancel_invoice') }}</button>
        </form>
    </details>
</section>
@endcan
@endif
@endsection
