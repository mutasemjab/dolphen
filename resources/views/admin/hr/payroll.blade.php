@extends('admin.layouts.factory')
@section('title', __('erp.payroll'))
@section('content')
<section class="panel">
    <form class="filters"><label>{{ __('erp.month') }}<input type="month" name="month" value="{{ $month }}" required></label><button class="btn">{{ __('erp.filter') }}</button></form>
    <div class="table-wrap"><table><thead><tr>
        @foreach(['employee_id','salary','overtime_pay','holiday_overtime_pay','advance_deduction','net','paid_on','actions'] as $field)<th>{{ __('erp.'.$field) }}</th>
        @endforeach
    </tr></thead><tbody>
    @forelse($payrolls as $row)
        <tr><td><a href="{{ route('admin.payroll.show',$row) }}">{{ $row->employee->label }}</a></td>
        @foreach(['salary','overtime_pay','holiday_overtime_pay','advance_deduction','net'] as $field)<td>{{ \App\Support\Admin\Format::money($row->$field) }}</td>
        @endforeach
        <td>{{ $row->paid_on ?: __('erp.unpaid') }}</td><td class="actions">
        @can('erp.hr.manage')
        @if(!$row->paid_on)
            <form method="post" action="{{ route('admin.payroll.store') }}">@csrf
                @foreach(['employee_id','month','allowance','deduction','notes'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $row->$field }}">@endforeach
                <button class="btn small">{{ __('erp.recalculate') }}</button>
            </form>
            <form method="post" action="{{ route('admin.payroll.paid',$row) }}" data-confirm="{{ __('erp.confirm_paid') }}">@csrf<button class="btn small">{{ __('erp.mark_paid') }}</button></form>
            <form method="post" action="{{ route('admin.payroll.destroy',$row) }}" data-confirm="{{ __('erp.confirm_delete') }}">@csrf
                @method('delete')<button class="btn small danger-outline">{{ __('erp.remove') }}</button>
            </form>
        @endif
        @endcan</td></tr>
    @empty<tr><td colspan="8" class="empty">{{ __('erp.no_data') }}</td></tr>
    @endforelse
    </tbody></table></div>{{ $payrolls->links('admin.pagination') }}
</section>
@can('erp.hr.manage')
<section class="panel form-panel"><h2>{{ __('erp.calculate_salary') }}</h2>
    <form method="post" action="{{ route('admin.payroll.store') }}">@csrf
        <div class="form-grid">
            @include('admin.field',['field'=>'employee_id','rule'=>'required','choices'=>$employees->mapWithKeys(fn($e)=>[$e->id=>$e->label])->all()])
            <div class="field"><label for="salary-month">{{ __('erp.month') }}</label><input type="month" id="salary-month" name="month" value="{{ old('month',$month) }}" required></div>
            @include('admin.field',['field'=>'allowance','rule'=>'required|numeric','value'=>'0'])
            @include('admin.field',['field'=>'deduction','rule'=>'required|numeric','value'=>'0'])
        </div>
        @include('admin.field',['field'=>'notes'])
        <p class="hint">{{ __('erp.payroll_help') }}</p>
        <button class="btn primary">{{ __('erp.calculate_salary') }}</button>
    </form>
</section>
@endcan
@endsection
