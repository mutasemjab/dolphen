<div data-line>
    <input type="date" name="holidays[{{ $index }}][date]" value="{{ $holiday['date'] ?? '' }}" required aria-label="{{ __('erp.date') }}" style="max-width:170px">
    <input name="holidays[{{ $index }}][name]" value="{{ $holiday['name'] ?? '' }}" placeholder="{{ __('erp.holiday_name') }}" aria-label="{{ __('erp.holiday_name') }}" maxlength="200">
    <button class="btn small danger-outline" type="button" data-remove-line>{{ __('erp.remove') }}</button>
</div>
@error('holidays.'.$index.'.date')<span class="field-error">{{ $message }}</span>@enderror
