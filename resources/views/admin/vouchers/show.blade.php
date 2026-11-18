@extends('admin.layouts.factory')
@section('title', __('erp.'.$voucher->type).' · '.$voucher->number)
@section('actions')<button class="btn" type="button" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('admin.vouchers.index',$voucher->type) }}">{{ __('erp.back') }}</a>
@endsection
@section('content')
<section class="panel form-panel">
    <div class="document-meta">
        <div><span>{{ __('erp.date') }}</span><strong>{{ $voucher->date->format('Y-m-d') }}</strong></div>
        <div><span>{{ __('erp.warehouse_id') }}</span><strong>{{ $voucher->warehouse?->label }}</strong></div>
        @if($voucher->partner)<div><span>{{ __('erp.partner_id') }}</span><strong>{{ $voucher->partner->label }}</strong></div>@endif
        <div><span>{{ __('erp.origin') }}</span><strong>@if($voucher->source)<a href="{{ route('admin.invoices.show',$voucher->source) }}">{{ __('erp.'.$voucher->source->type) }} {{ $voucher->source->number }}</a>@else{{ __('erp.origin_manual') }}@endif</strong></div>
        <div><span>{{ __('erp.status') }}</span><strong>{{ __('erp.'.$voucher->status) }}</strong></div>
    </div>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['product_id','quantity','base_unit_id'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @foreach($voucher->lines as $line)
        <tr><td>{{ $line->product->label }}</td><td>{{ \App\Support\Admin\Format::number($line->quantity, 3) }}</td><td>{{ $line->unit->label }}</td></tr>
    @endforeach
    </tbody></table></div>
    <p class="mt-4">{{ $voucher->notes }}</p>
    @if($voucher->status === 'cancelled')<p class="alert danger">{{ $voucher->metadata['cancel_reason'] ?? '' }}</p>
    @endif
    <div class="signatures"><div>{{ __('erp.received_by') }}</div><div>{{ __('erp.storekeeper') }}</div></div>
</section>
@if($voucher->status === 'posted')
@can('erp.inventory.manage')
<section class="panel form-panel no-print">
    @if($voucher->source)
    <p class="hint">{{ __('erp.voucher_from_invoice') }}</p>
    @else
    <details><summary>{{ __('erp.cancel_voucher') }}</summary>
        <p class="hint">{{ __('erp.cancel_voucher_help') }}</p>
        <form method="post" action="{{ route('admin.vouchers.cancel',$voucher) }}" data-confirm="{{ __('erp.confirm_cancel_voucher') }}">@csrf
            @include('admin.field',['field'=>'reason','rule'=>'required'])
            <button class="btn danger-outline">{{ __('erp.cancel_voucher') }}</button>
        </form>
    </details>
    @endif
</section>
@endcan
@endif
@endsection
