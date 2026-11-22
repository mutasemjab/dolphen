@php
    $baseCurrency = \App\Models\Admin\Currency::base();
    $trimRate = fn($v) => str_contains((string) $v, '.') ? (rtrim(rtrim((string) $v, '0'), '.') ?: '0') : (string) $v;
    $selectedCurrency = old('currency', $currencyValue ?? $baseCurrency);
    $rateValue = old('exchange_rate', isset($rateValue) ? $trimRate($rateValue) : $trimRate(optional($currencies->firstWhere('code', $selectedCurrency))->exchange_rate ?? '1'));
@endphp
<div class="field">
    <label for="field-currency">{{ __('erp.currency') }} <span class="required">*</span></label>
    <select name="currency" id="field-currency" required data-currency-select data-base="{{ $baseCurrency }}">
        @foreach($currencies as $currencyOption)
        <option value="{{ $currencyOption->code }}" data-rate="{{ $currencyOption->code === $baseCurrency ? '1' : $trimRate($currencyOption->exchange_rate) }}" data-decimals="{{ $currencyOption->decimals }}" @selected($selectedCurrency === $currencyOption->code)>{{ $currencyOption->code }} · {{ $currencyOption->label }}</option>
        @endforeach
    </select>
    @error('currency')<span class="field-error">{{ $message }}</span>@enderror
</div>
<div class="field" data-rate-field>
    <label for="field-exchange_rate">{{ __('erp.exchange_rate') }} ({{ __('erp.rate_in_base', ['base' => $baseCurrency]) }})</label>
    <input id="field-exchange_rate" name="exchange_rate" value="{{ $rateValue }}" inputmode="decimal" dir="ltr" data-exchange-rate>
    @error('exchange_rate')<span class="field-error">{{ $message }}</span>@enderror
</div>
