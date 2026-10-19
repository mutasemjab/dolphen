@extends('admin.layouts.factory')
@section('title', __('erp.advances'))
@section('content')
<section class="panel">
    <form class="filters"><select name="employee_id" aria-label="{{ __('erp.employee_id') }}"><option value="">{{ __('erp.all') }}</option>
        @foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->label }}</option>@endforeach
    </select><button class="btn">{{ __('erp.filter') }}</button></form>
    <p class="hint">{{ __('erp.advance_help') }}</p>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['employee_id','date','amount','repaid','reserved','remaining','notes','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach
    </tr></thead><tbody>
        @forelse($advances as $row)
        @php($repaid = \App\Support\Admin\Decimal::sum($row->repayments->filter(fn($r)=>$r->payroll->paid_on)->pluck('amount')))
        @php($reserved = \App\Support\Admin\Decimal::sum($row->repayments->filter(fn($r)=>!$r->payroll->paid_on)->pluck('amount')))
        <tr><td>{{ $row->employee->label }}</td><td>{{ $row->date }}</td><td>{{ \App\Support\Admin\Format::money($row->amount) }}</td>
            <td>{{ \App\Support\Admin\Format::money($repaid) }}</td><td>{{ \App\Support\Admin\Format::money($reserved) }}</td>
            <td>{{ \App\Support\Admin\Format::money(\App\Support\Admin\Decimal::sub($row->amount,$repaid)) }}</td><td>{{ $row->notes }}</td>
            <td>@can('erp.hr.manage')@if($row->repayments->isEmpty())
            <form method="post" action="{{ route('admin.hr.advances.destroy',$row) }}" data-confirm="{{ __('erp.confirm_delete_advance') }}">@csrf @method('delete')<button class="btn small danger-outline">{{ __('erp.remove') }}</button></form>
            @endif @endcan</td></tr>
        @empty<tr><td colspan="8" class="empty">{{ __('erp.no_data') }}</td></tr>@endforelse
    </tbody></table></div>{{ $advances->links('admin.pagination') }}
</section>
@can('erp.hr.manage')
<section class="panel form-panel"><h2>{{ __('erp.add_advance') }}</h2>
    <form method="post" action="{{ route('admin.hr.advances.store') }}">@csrf
        <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="form-grid">
            @include('admin.field',['field'=>'employee_id','rule'=>'required','choices'=>$employees->where('active',true)->mapWithKeys(fn($e)=>[$e->id=>$e->label])->all()])
            @include('admin.field',['field'=>'date','rule'=>'required|date_format:Y-m-d','value'=>now()->toDateString()])
            @include('admin.field',['field'=>'amount','rule'=>'required|numeric'])
        </div>
        @include('admin.field',['field'=>'notes'])
        <button class="btn primary">{{ __('erp.save') }}</button>
    </form>
</section>
@endcan
@endsection
