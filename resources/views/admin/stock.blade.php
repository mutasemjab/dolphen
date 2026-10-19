@extends('admin.layouts.factory')
@section('title', __('erp.stock'))
@section('actions')<button type="button" class="btn" data-print>{{ __('erp.print') }}</button>
@endsection
@section('content')
<section class="panel">
    <form class="filters">
        <label>{{ __('erp.warehouse_id') }}<select name="warehouse_id">
            @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->label }}</option>
            @endforeach
        </select></label>
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('erp.search') }}" aria-label="{{ __('erp.search') }}">
        <button class="btn">{{ __('erp.filter') }}</button>
    </form>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['product_id','base_unit_id','quantity','minimum_stock'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @forelse($products as $product)
        <tr><td>{{ $product->label }}</td><td>{{ $product->baseUnit->label }}</td><td>{{ \App\Support\Admin\Format::number($product->quantity ?? '0', 3) }}</td>
        <td>{{ $product->minimum_stock }} @if(\App\Support\Admin\Decimal::cmp((string)($product->quantity ?? '0'),$product->minimum_stock)<=0)<span class="badge">{{ __('erp.low_stock') }}</span>
        @endif</td></tr>
    @empty<tr><td colspan="4" class="empty">{{ __('erp.no_data_create') }}</td></tr>
    @endforelse
    </tbody></table></div>{{ $products->links('admin.pagination') }}
</section>
@can('erp.inventory.manage')
<section class="panel form-panel no-print">
    <details><summary>{{ __('erp.adjust_stock') }}</summary><p class="hint">{{ __('erp.adjust_help') }}</p>
    <form method="post" action="{{ route('admin.stock.adjust') }}">@csrf
        <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="form-grid">
            @include('admin.field',['field'=>'warehouse_id','rule'=>'required','value'=>$warehouseId,'choices'=>$warehouses->mapWithKeys(fn($w)=>[$w->id=>$w->label])->all()])
            @include('admin.field',['field'=>'product_id','rule'=>'required','choices'=>$choices->mapWithKeys(fn($p)=>[$p->id=>$p->label])->all()])
            @include('admin.field',['field'=>'quantity','rule'=>'required|numeric'])
        </div>
        @include('admin.field',['field'=>'notes','rule'=>'required'])
        <button class="btn primary">{{ __('erp.save') }}</button>
    </form></details>
</section>
@endcan
<section class="panel"><div class="panel-heading"><h2>{{ __('erp.recent_movements') }}</h2></div>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['date','product_id','quantity','type','number'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @forelse($movements as $movement)
        <tr><td>{{ $movement->date }}</td><td>{{ $movement->product->label }}</td><td dir="ltr">{{ $movement->quantity }}</td><td>{{ __('erp.'.$movement->kind) }}</td>
        <td>{{ $movement->invoice->number }}</td></tr>
    @empty<tr><td colspan="5" class="empty">{{ __('erp.no_data') }}</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endsection
