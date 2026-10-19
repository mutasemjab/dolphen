@php
    $inputName = $inputName ?? $field;
    $errorKey = str_replace(['[', ']'], ['.', ''], $inputName);
    $value = old($errorKey, $value ?? (isset($record) ? $record->getAttribute($field) : null));
    $choices = $choices ?? ($options[$field] ?? null);
    $rule = $rule ?? '';
    if (!$choices && preg_match('/(?:^|\|)in:([^|]+)/', $rule, $match)) {
        $choices = collect(explode(',', $match[1]))
            ->mapWithKeys(fn($v) => [$v => __('erp.' . $v)])
            ->all();
    }
    if (str_contains($rule, 'boolean')) {
        $choices = [1 => __('erp.yes'), 0 => __('erp.no')];
        $value ??= 1;
    }
    if (in_array($field, ['password', 'password_confirmation'])) { $value = ''; }
    if (str_contains($rule, 'date_format:H:i') && $value) { $value = substr($value, 0, 5); }
    $numeric = str_contains($rule, 'numeric') || str_contains($rule, 'integer');
    if ($numeric && is_string($value) && str_contains($value, '.') && is_numeric($value)) { $value = rtrim(rtrim($value, '0'), '.'); }
    $type = $numeric
        ? 'text'
        : (str_contains($rule, 'date_format:Y-m-d')
            ? 'date'
            : (str_contains($rule, 'date_format:H:i')
                ? 'time'
                : (str_contains($rule, 'email')
                    ? 'email'
                    : 'text')));
    $required = str_starts_with($rule, 'required');
    if (in_array($field, ['password', 'password_confirmation'])) { $type = 'password'; }
    $fieldId = 'field-' . str_replace(['[', ']'], ['-', ''], $inputName);
@endphp
<div class="field">
    <label for="{{ $fieldId }}">{{ __('erp.' . $field) }} @if ($required)
            <span class="required">*</span>
        @endif
    </label>
    @if (is_array($choices))
        <select name="{{ $inputName }}" id="{{ $fieldId }}" {{ $required ? 'required' : '' }}>
            <option value="">{{ __('erp.select') }}</option>
            @foreach ($choices as $key => $label)
                <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif(in_array($field, ['notes', 'specifications', 'address', 'waste_reason', 'reason']))
        <textarea id="{{ $fieldId }}" name="{{ $inputName }}" rows="3" {{ $required ? 'required' : '' }}>{{ $value }}</textarea>
    @else
        <input type="{{ $type }}" id="{{ $fieldId }}" name="{{ $inputName }}"
            value="{{ $value }}" {{ $required ? 'required' : '' }}
            @if($type === 'password') autocomplete="new-password" minlength="6" @endif
            @if ($numeric) inputmode="decimal" dir="ltr" @endif>
    @endif
    @error($errorKey)
        <span class="field-error">{{ $message }}</span>
    @enderror
</div>
