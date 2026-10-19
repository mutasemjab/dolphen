@extends('admin.layouts.factory')
@section('title', __('erp.'.$invoice->type).' · '.$invoice->number)
@section('actions')<button class="btn" type="button" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('admin.invoices.index',$invoice->type) }}">{{ __('erp.back') }}</a>
@endsection
@section('content')
<section class="panel form-panel">
    <div class="document-meta">
        <div><span>{{ __('erp.date') }}</span><strong>{{ $invoice->date->format('Y-m-d') }}</strong></div>
        <div><span>{{ __('erp.partner_id') }}</span><strong>{{ $invoice->partner?->label }}</strong></div>
        <div><span>{{ __('erp.warehouse_id') }}</span><strong>{{ $invoice->warehouse?->label }}</strong></div>
        <div><span>{{ __('erp.status') }}</span><strong>{{ __('erp.'.$invoice->status) }}</strong></div>
    </div>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['product_id','quantity','base_unit_id','unit_price','total'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @foreach($invoice->lines as $line)
        <tr><td>{{ $line->product->label }}</td><td>{{ $line->quantity }}</td><td>{{ $line->unit->label }}</td><td>{{ \App\Support\Admin\Format::money($line->unit_price) }}</td><td>{{ \App\Support\Admin\Format::money($line->total) }}</td></tr>
    @endforeach
    </tbody></table></div>
    <div class="document-totals">
        @foreach(['subtotal'=>$invoice->subtotal,'discount'=>$invoice->metadata['discount'] ?? '0','tax_total'=>$invoice->tax_total,'total'=>$invoice->total,'paid'=>$invoice->paid] as $label=>$value)
        <div><span>{{ __('erp.'.$label) }}</span><strong>{{ \App\Support\Admin\Format::money($value) }} {{ $invoice->currency }}</strong></div>
        @endforeach
        @if($invoice->status === 'posted')
        <div><span>{{ __('erp.remaining') }}</span><strong>{{ \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($invoice->total,$invoice->paid)) }} {{ $invoice->currency }}</strong></div>
        @endif
    </div>
    <p>{{ $invoice->notes }}</p>
    @if($invoice->status === 'cancelled')<p class="alert danger">{{ $invoice->metadata['cancel_reason'] ?? '' }}</p>
    @endif
</section>
@if($invoice->status === 'posted')
@can('erp.'.\App\Services\Admin\InvoiceService::TYPES[$invoice->type].'.manage')
<section class="panel form-panel no-print">
    <h2>{{ __('erp.record_payment') }}</h2>
    <form method="post" action="{{ route('admin.invoices.pay',$invoice) }}">@csrf
        <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="form-grid">@include('admin.field',['field'=>'amount','rule'=>'required|numeric'])</div>
        <button class="btn primary">{{ __('erp.record_payment') }}</button>
    </form>
    <details class="mt-4"><summary>{{ __('erp.cancel_invoice') }}</summary>
        <p class="hint">{{ __('erp.cancel_help') }}</p>
        <form method="post" action="{{ route('admin.invoices.cancel',$invoice) }}" data-confirm="{{ __('erp.confirm_cancel') }}">@csrf
            @include('admin.field',['field'=>'reason','rule'=>'required'])
            <button class="btn danger-outline">{{ __('erp.cancel_invoice') }}</button>
        </form>
    </details>
</section>
@endcan
@endif
@endsection
