<div class="metric-grid leave-balance">
    @foreach(['allowance'=>'annual_leave_days','used'=>'leave_used','reserved'=>'leave_reserved','available'=>'leave_available'] as $key=>$label)
    <div class="metric"><span>{{ __('erp.'.$label) }} · {{ $balance['year'] }}</span><strong>{{ \App\Support\Admin\Format::number($balance[$key],3) }} <small>{{ __('erp.days_unit') }}</small></strong></div>
    @endforeach
</div>
