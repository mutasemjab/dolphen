@extends('admin.layouts.factory')
@section('title', __('erp.'.$resource))
@section('actions')
@if(in_array($resource, ['products','customers','suppliers']))
<a class="btn" href="{{ route('admin.masters.index', ['products'=>'product_categories','customers'=>'customer_categories','suppliers'=>'supplier_categories'][$resource]) }}">{{ __('erp.categories') }}</a>
@endif
@can('erp.'.$definition['module'].'.manage')
<a class="btn primary" href="{{ route('admin.masters.create', $resource) }}">+ {{ __('erp.create') }}</a>
@endcan
@endsection
@section('content')
<section class="panel">
    <form class="filters">
    @if($resource === 'attendance')
        <label>{{ __('erp.date') }}<input type="date" name="date" value="{{ request('date') }}"></label>
    @else
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('erp.search') }}" aria-label="{{ __('erp.search') }}">
    @endif
    @if(isset($options['classification_id']))
    <select name="classification_id" aria-label="{{ __('erp.classification_id') }}"><option value="">{{ __('erp.all_categories') }}</option>
        @foreach($options['classification_id'] as $id=>$name)<option value="{{ $id }}" @selected(request('classification_id') == $id)>{{ $name }}</option>@endforeach
    </select>
    @endif
    @if($resource === 'products')
    <select name="item_type" aria-label="{{ __('erp.item_type') }}"><option value="">{{ __('erp.all_item_types') }}</option>
        @foreach(['finished','raw'] as $itemType)<option value="{{ $itemType }}" @selected(request('item_type') === $itemType)>{{ __('erp.'.$itemType) }}</option>@endforeach
    </select>
    <select name="warehouse_id" aria-label="{{ __('erp.warehouse_id') }}"><option value="">{{ __('erp.all_warehouses') }}</option>
        @foreach($options['warehouse_id'] ?? [] as $id=>$name)<option value="{{ $id }}" @selected(request('warehouse_id') == $id)>{{ $name }}</option>@endforeach
    </select>
    @endif
    <button class="btn">{{ __('erp.filter') }}</button></form>
    @php($columns = array_slice(array_values(array_diff(array_keys($definition['fields']), ['password','password_confirmation'])), 0, 9))
    <div class="table-wrap"><table><thead><tr>
        @foreach($columns as $column)<th>{{ __('erp.'.$column) }}</th>
        @endforeach
        <th>{{ __('erp.actions') }}</th>
    </tr></thead><tbody>
    @forelse($records as $record)
        <tr>
        @foreach($columns as $column)
            <td>@if(isset($options[$column])){{ $options[$column][$record->$column] ?? '—' }}
                @elseif($column === 'active'){{ __('erp.'.($record->$column ? 'yes' : 'no')) }}
                @elseif(in_array($column, ['status','item_type','worker_type']) && $record->$column){{ __('erp.'.$record->$column) }}
                @elseif($column === 'selling_price' && $record->item_type === 'raw')—
                @else{{ $record->$column }}@endif</td>
        @endforeach
        <td>@can('erp.'.$definition['module'].'.manage')
            <a class="btn small" href="{{ route('admin.masters.edit',[$resource,$record->id]) }}">{{ __('erp.edit') }}</a>
        @endcan
        @if(in_array($resource, ['customers','suppliers']))
            <a class="btn small" href="{{ route('admin.statements', [$resource, 'partner_id' => $record->id]) }}">{{ __('erp.account_statement') }}</a>
        @endif</td></tr>
    @empty<tr><td class="empty" colspan="{{ count($columns)+1 }}">{{ __('erp.no_data_create') }}</td></tr>
    @endforelse
    </tbody></table></div>{{ $records->links('admin.pagination') }}
</section>
@endsection
