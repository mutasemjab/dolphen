@extends('admin.layouts.factory')
@section('title', __('erp.overview'))
@section('subtitle', __('erp.month_overview'))
@section('actions')
@can('erp.sales.manage')<a class="btn primary" href="{{ route('admin.invoices.create', 'sales_invoice') }}">+ {{ __('erp.sales_invoice') }}</a>
@endcan
@can('erp.purchasing.manage')<a class="btn" href="{{ route('admin.invoices.create', 'purchase_invoice') }}">+ {{ __('erp.purchase_invoice') }}</a>
@endcan
@endsection
@section('content')
<div class="metric-grid">
@foreach($cards as $label => $value)
    <div class="metric"><span>{{ __('erp.'.$label) }}</span><strong>{{ in_array($label, ['products','employees']) ? $value : \App\Support\Admin\Format::money($value).' '.\App\Models\Admin\Currency::base() }}</strong></div>
@endforeach
</div>
<section class="panel">
    <div class="panel-heading"><h2>{{ __('erp.recent_invoices') }}</h2></div>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['number','date','type','partner_id','total','status'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @forelse($invoices as $invoice)
        <tr><td><a href="{{ route('admin.invoices.show',$invoice) }}">{{ $invoice->number }}</a></td><td>{{ $invoice->date->format('Y-m-d') }}</td>
        <td>{{ __('erp.'.$invoice->type) }}</td><td>{{ $invoice->partner?->label }}</td><td>{{ \App\Support\Admin\Format::money($invoice->total, $invoice->currency) }} {{ $invoice->currency }}</td>
        <td><span class="badge {{ $invoice->status }}">{{ __('erp.'.$invoice->status) }}</span></td></tr>
    @empty<tr><td colspan="6" class="empty">{{ __('erp.no_invoices') }}</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endsection
