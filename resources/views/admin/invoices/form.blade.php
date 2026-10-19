@extends('admin.layouts.factory')
@section('title', __('erp.new_invoice').' · '.__('erp.'.$type))
@section('content')
<form method="post" action="{{ route('admin.invoices.store',$type) }}" class="panel form-panel">
    @csrf
    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="form-grid">
        @include('admin.field',['field'=>'date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
        @include('admin.field',['field'=>'partner_id','rule'=>'required','choices'=>$partners->mapWithKeys(fn($p)=>[$p->id=>$p->label])->all()])
        @include('admin.field',['field'=>'warehouse_id','rule'=>'required','choices'=>$warehouses->mapWithKeys(fn($w)=>[$w->id=>$w->label])->all()])
    </div>
    <div class="table-wrap line-editor"><table><thead><tr>
        @foreach(['product_id','quantity','unit_price','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody id="invoice-lines">
    @foreach(old('lines',[[]]) as $index => $line)
        @include('admin.invoices.line',compact('index','line'))
    @endforeach
    </tbody></table></div>
    <button class="btn" type="button" data-add-line="line-template" data-target="invoice-lines">+ {{ __('erp.add_line') }}</button>
    <template id="line-template">@include('admin.invoices.line',['index'=>'__INDEX__','line'=>[]])</template>
    <div class="form-grid mt-4">
        @include('admin.field',['field'=>'discount','rule'=>'required|numeric','value'=>'0'])
        @include('admin.field',['field'=>'tax_rate','rule'=>'required|numeric','value'=>'0'])
        @include('admin.field',['field'=>'paid','rule'=>'required|numeric','value'=>'0'])
    </div>
    @include('admin.field',['field'=>'notes'])
    <p class="hint">{{ __('erp.invoice_help') }}</p>
    <div class="form-footer"><button class="btn primary">{{ __('erp.save_invoice') }}</button><a class="btn" href="{{ route('admin.invoices.index',$type) }}">{{ __('erp.back') }}</a></div>
</form>
@endsection
