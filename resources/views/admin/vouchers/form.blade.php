@extends('admin.layouts.factory')
@section('title', __('erp.new_'.$type))
@section('content')
<form method="post" action="{{ route('admin.vouchers.store',$type) }}" class="panel form-panel">
    @csrf
    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="form-grid">
        @include('admin.field',['field'=>'date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
        @include('admin.field',['field'=>'warehouse_id','rule'=>'required','choices'=>$warehouses->mapWithKeys(fn($w)=>[$w->id=>$w->label])->all()])
        @include('admin.field',['field'=>'partner_id','rule'=>'nullable','choices'=>$partners->mapWithKeys(fn($p)=>[$p->id=>$p->label])->all()])
    </div>
    <div class="table-wrap line-editor"><table><thead><tr>
        @foreach(['product_id','quantity','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody id="voucher-lines">
    @foreach(old('lines',[[]]) as $index => $line)
        @include('admin.vouchers.line',compact('index','line'))
    @endforeach
    </tbody></table></div>
    <button class="btn" type="button" data-add-line="voucher-line-template" data-target="voucher-lines">+ {{ __('erp.add_line') }}</button>
    <template id="voucher-line-template">@include('admin.vouchers.line',['index'=>'__INDEX__','line'=>[]])</template>
    @include('admin.field',['field'=>'notes'])
    <p class="hint">{{ __('erp.'.$type.'_help') }}</p>
    <div class="form-footer"><button class="btn primary">{{ __('erp.save') }}</button><a class="btn" href="{{ route('admin.vouchers.index',$type) }}">{{ __('erp.back') }}</a></div>
</form>
@endsection
