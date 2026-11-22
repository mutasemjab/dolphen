@extends('admin.layouts.factory')
@section('title', __('erp.'.$type))
@php($proformaList = $type === 'proforma_invoice')
@section('actions')
@can('erp.'.\App\Services\Admin\InvoiceService::FORMS[$type].'.manage')
<a class="btn primary" href="{{ route('admin.invoices.create',$type) }}">+ {{ __('erp.new_'.$type) }}</a>
@endcan
<button class="btn" type="button" data-print>{{ __('erp.print') }}</button>
@endsection
@section('content')
<section class="panel">
<form class="filters">
    <input name="q" value="{{ request('q') }}" placeholder="{{ __('erp.number') }}" aria-label="{{ __('erp.number') }}">
    <label>{{ __('erp.partner_id') }}<select name="partner_id"><option value="">{{ __('erp.all') }}</option>
        @foreach($partners as $partner)<option value="{{ $partner->id }}" @selected(request('partner_id') == $partner->id)>{{ $partner->label }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.from') }}<input type="date" name="from" value="{{ request('from') }}"></label>
    <label>{{ __('erp.to') }}<input type="date" name="to" value="{{ request('to') }}"></label><button class="btn">{{ __('erp.filter') }}</button>
</form>
<div class="summary-grid">
@forelse($totals as $code => $sum)
<div>{{ __($proformaList ? 'erp.open_proformas_total' : 'erp.total') }} ({{ $code }}) <strong>{{ \App\Support\Admin\Format::money($sum['total'], $code) }}</strong></div>
@unless($proformaList)<div>{{ __('erp.remaining') }} ({{ $code }}) <strong>{{ \App\Support\Admin\Format::money($sum['remaining'], $code) }}</strong></div>@endunless
@empty<div>{{ __('erp.total') }} <strong>0</strong></div>
@endforelse
</div>
@php($columns = $proformaList ? ['number','date','partner_id','warehouse_id','total','valid_until','status'] : ['number','date','partner_id','warehouse_id','total','paid','remaining','status'])
<div class="table-wrap"><table><thead><tr>
@foreach($columns as $field)<th>{{ __('erp.'.$field) }}</th>
@endforeach
</tr></thead><tbody>
@forelse($invoices as $invoice)
<tr><td><a href="{{ route('admin.invoices.show',$invoice) }}">{{ $invoice->number }}</a></td><td>{{ $invoice->date->format('Y-m-d') }}</td><td>{{ $invoice->partner?->label }}</td><td>{{ $invoice->warehouse?->label }}</td>
<td>{{ \App\Support\Admin\Format::money($invoice->total, $invoice->currency) }} {{ $invoice->currency }}</td>
@if($proformaList)
<td>{{ $invoice->metadata['valid_until'] ?? '—' }}</td>
@else
<td>{{ \App\Support\Admin\Format::money($invoice->paid, $invoice->currency) }}</td><td>{{ $invoice->status === 'cancelled' ? '—' : \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($invoice->total,$invoice->paid), $invoice->currency) }}</td>
@endif
<td><span class="badge {{ $invoice->status }}">{{ __('erp.'.$invoice->status) }}</span></td></tr>
@empty<tr><td class="empty" colspan="{{ count($columns) }}">{{ __('erp.no_invoices') }}</td></tr>
@endforelse
</tbody></table></div>{{ $invoices->links('admin.pagination') }}
</section>
@endsection
