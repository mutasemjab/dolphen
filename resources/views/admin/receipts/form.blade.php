@extends('admin.layouts.factory')
@section('title', __('erp.new_cash_receipt'))
@section('content')
@php($remaining = $invoice ? \App\Support\Admin\Decimal::sub($invoice->total, $invoice->paid) : null)
<form method="post" action="{{ route('admin.receipts.store') }}" class="panel form-panel" data-receipt-form>
    @csrf
    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="form-grid">
        @include('admin.field',['field'=>'date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
        @include('admin.field',['field'=>'partner_id','rule'=>'required','value'=>$invoice?->partner_id,'choices'=>$partners->mapWithKeys(fn($p)=>[$p->id=>$p->label])->all()])
        @include('admin.currency-fields',['currencyValue'=>$invoice?->currency])
        @include('admin.field',['field'=>'amount','rule'=>'required|numeric','value'=>$remaining !== null ? (rtrim(rtrim($remaining, '0'), '.') ?: '0') : null])
        @include('admin.field',['field'=>'payment_method','rule'=>'required|in:cash,cheque,bank_transfer','value'=>'cash'])
        <div class="field" data-receipt-invoice>
            <label for="field-invoice_id">{{ __('erp.apply_to_invoice') }}</label>
            <select name="invoice_id" id="field-invoice_id">
                <option value="">{{ __('erp.oldest_invoices_first') }}</option>
                @foreach($open as $row)
                <option value="{{ $row->id }}" data-partner="{{ $row->partner_id }}" data-currency="{{ $row->currency }}" @selected((string) old('invoice_id', $invoice?->id) === (string) $row->id)>{{ $row->number }} · {{ $row->date->format('Y-m-d') }} · {{ __('erp.remaining') }} {{ \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($row->total, $row->paid), $row->currency) }} {{ $row->currency }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-grid" data-cheque-fields>
        @include('admin.field',['field'=>'cheque_number','rule'=>'nullable'])
        @include('admin.field',['field'=>'cheque_date','rule'=>'nullable|date_format:Y-m-d'])
        @include('admin.field',['field'=>'bank_name','rule'=>'nullable'])
    </div>
    @include('admin.field',['field'=>'notes'])
    <p class="hint">{{ __('erp.receipt_help') }}</p>
    <div class="form-footer"><button class="btn primary">{{ __('erp.save') }}</button><a class="btn" href="{{ $invoice ? route('admin.invoices.show', $invoice) : route('admin.receipts.index') }}">{{ __('erp.back') }}</a></div>
</form>
@endsection
