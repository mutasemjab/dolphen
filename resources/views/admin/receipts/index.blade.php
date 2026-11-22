@extends('admin.layouts.factory')
@section('title', __('erp.cash_receipts'))
@section('actions')
@can('erp.sales.manage')
<a class="btn primary" href="{{ route('admin.receipts.create') }}">+ {{ __('erp.new_cash_receipt') }}</a>
@endcan
<button class="btn" type="button" data-print>{{ __('erp.print') }}</button>
@endsection
@section('content')
<section class="panel">
<form class="filters">
    <input name="q" value="{{ request('q') }}" placeholder="{{ __('erp.receipt_number') }}" aria-label="{{ __('erp.receipt_number') }}">
    <label>{{ __('erp.customer') }}<select name="partner_id"><option value="">{{ __('erp.all') }}</option>
        @foreach($partners as $partner)<option value="{{ $partner->id }}" @selected(request('partner_id') == $partner->id)>{{ $partner->label }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.currency') }}<select name="currency"><option value="">{{ __('erp.all') }}</option>
        @foreach($currencies as $currency)<option value="{{ $currency->code }}" @selected(request('currency') === $currency->code)>{{ $currency->code }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.payment_method') }}<select name="payment_method"><option value="">{{ __('erp.all') }}</option>
        @foreach(\App\Services\Admin\InvoiceService::PAYMENT_METHODS as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ __('erp.'.$method) }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.from') }}<input type="date" name="from" value="{{ request('from') }}"></label>
    <label>{{ __('erp.to') }}<input type="date" name="to" value="{{ request('to') }}"></label><button class="btn">{{ __('erp.filter') }}</button>
</form>
<div class="summary-grid">
@forelse($totals as $code => $sum)<div>{{ __('erp.total') }} ({{ $code }}) <strong>{{ \App\Support\Admin\Format::money($sum, $code) }}</strong></div>
@empty<div>{{ __('erp.total') }} <strong>0</strong></div>
@endforelse
</div>
<div class="table-wrap"><table><thead><tr>
@foreach(['receipt_number','date','customer','payment_method','amount','allocated','unallocated','status'] as $field)<th>{{ __('erp.'.$field) }}</th>
@endforeach
</tr></thead><tbody>
@forelse($receipts as $receipt)
<tr><td><a href="{{ route('admin.receipts.show',$receipt) }}">{{ $receipt->number }}</a></td><td>{{ $receipt->date->format('Y-m-d') }}</td><td>{{ $receipt->partner?->label }}</td>
<td>{{ __('erp.'.($receipt->metadata['payment_method'] ?? 'cash')) }}</td>
<td>{{ \App\Support\Admin\Format::money($receipt->total, $receipt->currency) }} {{ $receipt->currency }}</td>
<td>{{ \App\Support\Admin\Format::money($receipt->paid, $receipt->currency) }}</td>
<td>{{ $receipt->status === 'cancelled' ? '—' : \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($receipt->total, $receipt->paid), $receipt->currency) }}</td>
<td><span class="badge {{ $receipt->status }}">{{ __('erp.'.$receipt->status) }}</span></td></tr>
@empty<tr><td class="empty" colspan="8">{{ __('erp.no_data_create') }}</td></tr>
@endforelse
</tbody></table></div>{{ $receipts->links('admin.pagination') }}
</section>
@endsection
