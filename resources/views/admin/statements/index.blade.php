@extends('admin.layouts.factory')
@section('title', __('erp.statement_'.$kind))
@section('actions')<button class="btn" type="button" data-print>{{ __('erp.print') }}</button>@endsection
@section('content')
<section class="panel">
<form class="filters">
    <label>{{ __('erp.partner_id') }}<select name="partner_id" required><option value="">{{ __('erp.select') }}</option>
        @foreach($partners as $partner)<option value="{{ $partner->id }}">{{ $partner->label }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.from') }}<input type="date" name="from"></label>
    <label>{{ __('erp.to') }}<input type="date" name="to"></label>
    <button class="btn primary">{{ __('erp.show_statement') }}</button>
</form>
<div class="table-wrap"><table><thead><tr>
    <th>{{ __('erp.name') }}</th><th>{{ __('erp.phone') }}</th><th>{{ __('erp.balance_'.$kind) }} ({{ $base }})</th><th class="no-print">{{ __('erp.actions') }}</th>
</tr></thead><tbody>
@forelse($partners as $partner)
<tr><td>{{ $partner->label }}</td><td dir="ltr">{{ $partner->phone }}</td><td>{{ \App\Support\Admin\Format::money($balances[$partner->id] ?? '0') }}</td>
<td class="no-print"><a class="btn small" href="{{ route('admin.statements', [$kind, 'partner_id' => $partner->id]) }}">{{ __('erp.account_statement') }}</a></td></tr>
@empty<tr><td class="empty" colspan="4">{{ __('erp.no_data') }}</td></tr>
@endforelse
</tbody></table></div>
<p class="hint" style="padding:0 20px">{{ __('erp.statement_help_'.$kind) }}</p>
</section>
@endsection
