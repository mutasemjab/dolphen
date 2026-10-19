@extends('admin.layouts.factory')
@section('title', __('erp.'.$type))
@section('actions')
@can('erp.'.\App\Services\Admin\InvoiceService::TYPES[$type].'.manage')
<a class="btn primary" href="{{ route('admin.invoices.create',$type) }}">+ {{ __('erp.new_invoice') }}</a>
@endcan
<button class="btn" type="button" data-print>{{ __('erp.print') }}</button>
@endsection
@section('content')
<section class="panel">
<form class="filters">
    <input name="q" value="{{ request('q') }}" placeholder="{{ __('erp.number') }}" aria-label="{{ __('erp.number') }}">
    <label>{{ __('erp.from') }}<input type="date" name="from" value="{{ request('from') }}"></label>
    <label>{{ __('erp.to') }}<input type="date" name="to" value="{{ request('to') }}"></label><button class="btn">{{ __('erp.filter') }}</button>
</form>
<div class="summary-grid"><div>{{ __('erp.total') }} <strong>{{ \App\Support\Admin\Format::money($total) }}</strong></div><div>{{ __('erp.remaining') }} <strong>{{ \App\Support\Admin\Format::money($remaining) }}</strong></div></div>
<div class="table-wrap"><table><thead><tr>
@foreach(['number','date','partner_id','warehouse_id','total','paid','remaining','status'] as $field)<th>{{ __('erp.'.$field) }}</th>
@endforeach
</tr></thead><tbody>
@forelse($invoices as $invoice)
<tr><td><a href="{{ route('admin.invoices.show',$invoice) }}">{{ $invoice->number }}</a></td><td>{{ $invoice->date->format('Y-m-d') }}</td><td>{{ $invoice->partner?->label }}</td><td>{{ $invoice->warehouse?->label }}</td>
<td>{{ \App\Support\Admin\Format::money($invoice->total) }}</td><td>{{ \App\Support\Admin\Format::money($invoice->paid) }}</td><td>{{ $invoice->status === 'cancelled' ? '—' : \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($invoice->total,$invoice->paid)) }}</td><td><span class="badge {{ $invoice->status }}">{{ __('erp.'.$invoice->status) }}</span></td></tr>
@empty<tr><td class="empty" colspan="8">{{ __('erp.no_invoices') }}</td></tr>
@endforelse
</tbody></table></div>{{ $invoices->links('admin.pagination') }}
</section>
@endsection
