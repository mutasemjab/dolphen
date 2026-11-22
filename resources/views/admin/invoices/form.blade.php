@extends('admin.layouts.factory')
@section('title', ($proforma ? __('erp.convert_to_sales_invoice').' · '.$proforma->number : __('erp.new_'.$type)))
@section('content')
@php
    $trim = fn($v) => str_contains((string) $v, '.') ? (rtrim(rtrim((string) $v, '0'), '.') ?: '0') : (string) $v;
    $proformaLines = $proforma?->lines->map(fn($l) => ['product_id' => $l->product_id, 'quantity' => $trim($l->quantity), 'unit_price' => $trim($l->unit_price)])->all();
@endphp
<form method="post" action="{{ route('admin.invoices.store',$type) }}" class="panel form-panel">
    @csrf
    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
    @if($proforma)
    <input type="hidden" name="proforma_id" value="{{ $proforma->id }}">
    <p class="hint">{{ __('erp.convert_help') }}</p>
    @endif
    <div class="form-grid">
        @include('admin.field',['field'=>'date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
        @include('admin.field',['field'=>'partner_id','rule'=>'required','value'=>$proforma?->partner_id,'choices'=>$partners->mapWithKeys(fn($p)=>[$p->id=>$p->label])->all()])
        @include('admin.field',['field'=>'warehouse_id','rule'=>'required','value'=>$proforma?->warehouse_id,'choices'=>$warehouses->mapWithKeys(fn($w)=>[$w->id=>$w->label])->all()])
        @include('admin.currency-fields',['currencyValue'=>$proforma?->currency,'rateValue'=>$proforma && $proforma->currency !== \App\Models\Admin\Currency::base() ? $proforma->exchange_rate : null])
        @if($type === 'proforma_invoice')
        @include('admin.field',['field'=>'valid_until','rule'=>'nullable|date_format:Y-m-d'])
        @endif
    </div>
    <p class="hint">{{ __('erp.currency_price_help', ['base' => \App\Models\Admin\Currency::base()]) }}</p>
    <div class="table-wrap line-editor"><table><thead><tr>
        @foreach(['product_id','quantity','unit_price','line_total','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody id="invoice-lines" data-invoice-lines>
    @foreach(old('lines', $proformaLines ?: [[]]) as $index => $line)
        @include('admin.invoices.line',compact('index','line'))
    @endforeach
    </tbody></table></div>
    <button class="btn" type="button" data-add-line="line-template" data-target="invoice-lines">+ {{ __('erp.add_line') }}</button>
    <template id="line-template">@include('admin.invoices.line',['index'=>'__INDEX__','line'=>[]])</template>
    <div class="form-grid mt-4">
        @include('admin.field',['field'=>'discount','rule'=>'required|numeric','value'=>$proforma ? $trim($proforma->metadata['discount'] ?? '0') : '0'])
        @include('admin.field',['field'=>'tax_rate','rule'=>'required|numeric','value'=>$proforma ? $trim($proforma->metadata['tax_rate'] ?? '0') : '0'])
        @if($type !== 'proforma_invoice')
        @include('admin.field',['field'=>'paid','rule'=>'required|numeric','value'=>'0'])
        @endif
    </div>
    <div class="document-totals" data-invoice-totals data-decimals="{{ \App\Models\Admin\Currency::decimalsFor(old('currency', $proforma?->currency)) }}">
        <div><span>{{ __('erp.subtotal') }}</span><strong data-total="subtotal">0</strong></div>
        <div><span>{{ __('erp.tax_total') }}</span><strong data-total="tax">0</strong></div>
        <div><span>{{ __('erp.total') }}</span><strong data-total="total">0</strong></div>
    </div>
    @include('admin.field',['field'=>'notes','value'=>$proforma?->notes])
    <p class="hint">{{ __($type === 'proforma_invoice' ? 'erp.proforma_help' : 'erp.invoice_help') }}</p>
    <div class="form-footer"><button class="btn primary">{{ __($type === 'proforma_invoice' ? 'erp.save' : 'erp.save_invoice') }}</button><a class="btn" href="{{ $proforma ? route('admin.invoices.show',$proforma) : route('admin.invoices.index',$type) }}">{{ __('erp.back') }}</a></div>
</form>
@endsection
