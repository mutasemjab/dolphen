@extends('admin.layouts.factory')
@section('title', __('erp.'.$type.'s'))
@section('actions')
@can('erp.inventory.manage')
<a class="btn primary" href="{{ route('admin.vouchers.create',$type) }}">+ {{ __('erp.new_'.$type) }}</a>
@endcan
<button class="btn" type="button" data-print>{{ __('erp.print') }}</button>
@endsection
@section('content')
<section class="panel">
<form class="filters">
    <input name="q" value="{{ request('q') }}" placeholder="{{ __('erp.voucher_number') }}" aria-label="{{ __('erp.voucher_number') }}">
    <label>{{ __('erp.warehouse_id') }}<select name="warehouse_id"><option value="">{{ __('erp.all_warehouses') }}</option>
        @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>{{ $warehouse->label }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.origin') }}<select name="origin"><option value="">{{ __('erp.all') }}</option>
        @foreach(['manual','invoice'] as $origin)<option value="{{ $origin }}" @selected(request('origin') === $origin)>{{ __('erp.origin_'.$origin) }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.from') }}<input type="date" name="from" value="{{ request('from') }}"></label>
    <label>{{ __('erp.to') }}<input type="date" name="to" value="{{ request('to') }}"></label><button class="btn">{{ __('erp.filter') }}</button>
</form>
<div class="table-wrap"><table><thead><tr>
@foreach(['voucher_number','date','warehouse_id','partner_id','origin','lines_count','status'] as $field)<th>{{ __('erp.'.$field) }}</th>
@endforeach
</tr></thead><tbody>
@forelse($vouchers as $voucher)
<tr><td><a href="{{ route('admin.vouchers.show',$voucher) }}">{{ $voucher->number }}</a></td><td>{{ $voucher->date->format('Y-m-d') }}</td><td>{{ $voucher->warehouse?->label }}</td><td>{{ $voucher->partner?->label ?? '—' }}</td>
<td>@if($voucher->source)<a href="{{ route('admin.invoices.show',$voucher->source) }}">{{ $voucher->source->number }}</a>@else{{ __('erp.origin_manual') }}@endif</td>
<td>{{ $voucher->lines_count }}</td><td><span class="badge {{ $voucher->status }}">{{ __('erp.'.$voucher->status) }}</span></td></tr>
@empty<tr><td class="empty" colspan="7">{{ __('erp.no_data_create') }}</td></tr>
@endforelse
</tbody></table></div>{{ $vouchers->links('admin.pagination') }}
</section>
@endsection
