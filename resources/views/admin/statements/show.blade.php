@extends('admin.layouts.factory')
@section('title', __('erp.account_statement').' · '.$partner->label)
@section('actions')<button class="btn" type="button" data-print>{{ __('erp.print') }}</button><a class="btn" href="{{ route('admin.statements', $kind) }}">{{ __('erp.back') }}</a>@endsection
@php($money = fn($v) => \App\Support\Admin\Format::money($v, $shown))
@section('content')
<section class="panel">
<form class="filters no-print">
    <label>{{ __('erp.partner_id') }}<select name="partner_id" required>
        @foreach($partners as $option)<option value="{{ $option->id }}" @selected($option->id === $partner->id)>{{ $option->label }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.currency') }}<select name="currency"><option value="">{{ __('erp.all_converted', ['base' => \App\Models\Admin\Currency::base()]) }}</option>
        @foreach($currencies as $option)<option value="{{ $option->code }}" @selected($currency === $option->code)>{{ $option->code }} · {{ $option->label }}</option>@endforeach
    </select></label>
    <label>{{ __('erp.from') }}<input type="date" name="from" value="{{ $from }}"></label>
    <label>{{ __('erp.to') }}<input type="date" name="to" value="{{ $to }}"></label>
    <button class="btn primary">{{ __('erp.show_statement') }}</button>
</form>
<div class="form-panel">
    <div class="document-meta">
        <div><span>{{ __('erp.'.($kind === 'customers' ? 'customer' : 'supplier')) }}</span><strong>{{ $partner->label }}</strong></div>
        @if($partner->phone)<div><span>{{ __('erp.phone') }}</span><strong dir="ltr">{{ $partner->phone }}</strong></div>@endif
        <div><span>{{ __('erp.period') }}</span><strong>{{ $from ?: __('erp.beginning') }} — {{ $to ?: now()->toDateString() }}</strong></div>
        <div><span>{{ __('erp.currency') }}</span><strong>{{ $currency ?: __('erp.all_converted', ['base' => $shown]) }}</strong></div>
    </div>
</div>
<div class="table-wrap"><table><thead><tr>
    @foreach(['date','number','description'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach
    @unless($currency)<th>{{ __('erp.original_amount') }}</th>@endunless
    @foreach(['debit','credit','balance'] as $field)<th>{{ __('erp.'.$field) }}</th>@endforeach
</tr></thead><tbody>
    <tr><td>{{ $from ?: '—' }}</td><td></td><td><strong>{{ __('erp.opening_balance') }}</strong></td>@unless($currency)<td></td>@endunless<td></td><td></td><td dir="ltr"><strong>{{ $money($opening) }}</strong></td></tr>
@forelse($rows as $row)
    @php($document = $row['invoice'])
    <tr><td>{{ $row['date'] }}</td><td><a href="{{ $document->showRoute() }}">{{ $document->number }}</a></td><td>{{ __('erp.'.$row['description']) }}</td>
    @unless($currency)<td dir="ltr">@if($document->currency !== $shown){{ \App\Support\Admin\Format::money($row['original'], $document->currency) }} {{ $document->currency }}@endif</td>@endunless
    <td dir="ltr">{{ \App\Support\Admin\Decimal::cmp($row['debit']) ? $money($row['debit']) : '' }}</td><td dir="ltr">{{ \App\Support\Admin\Decimal::cmp($row['credit']) ? $money($row['credit']) : '' }}</td><td dir="ltr">{{ $money($row['balance']) }}</td></tr>
@empty
    <tr><td class="empty" colspan="{{ $currency ? 6 : 7 }}">{{ __('erp.no_data') }}</td></tr>
@endforelse
</tbody><tfoot><tr>
    <th colspan="{{ $currency ? 3 : 4 }}">{{ __('erp.period_totals') }}</th><th dir="ltr">{{ $money($debit) }}</th><th dir="ltr">{{ $money($credit) }}</th><th dir="ltr">{{ $money($balance) }}</th>
</tr></tfoot></table></div>
<div class="summary-grid"><div>{{ __('erp.balance_'.$kind) }} <strong>{{ $money($balance) }} {{ $shown }}</strong></div></div>
</section>
@endsection
