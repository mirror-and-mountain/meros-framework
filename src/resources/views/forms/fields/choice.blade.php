@php 
    $allowsMultiple = $type === 'checkboxes';
    $fieldSetClass  = $isSubField ? 'nice-form-group !-mt-2.5' : 'nice-form-group';
    $classList = ' ' . $field->classList() . ' ';
    $isAdminField = str_contains($classList, ' meros-admin-field ');
    $fieldName = $field->getName(!$isSubField) . ($allowsMultiple ? '[]' : '');
    $emptyMarkerName = \MM\Meros\Support\FormFieldName::emptyMarkerName($fieldName);
@endphp

<fieldset id="{{ $id }}" class="{{ $fieldSetClass }}" data-field-type="{{ $field->handle }}">
    @if(!$isSubField && $label !== false)
        <legend 
            class="form-label"
        >
            {{ $label }}
            @if($isRequired)
                <span class="required-indicator">*</span>
            @endif
        </legend>
    @endif

    @if($helpText !== false && !empty($helpText))
        <small class="description">{{ $helpText }}</small>
    @endif

    @if($allowsMultiple && $isAdminField && is_string($emptyMarkerName))
        <input type="hidden" name="{{ $emptyMarkerName }}" value="1" data-checkboxes-empty-value="true" />
    @endif

    @foreach($options as $optValue => $label)
        <div class="nice-form-group">
            @php
                $optionValue = is_int($optValue) && !is_array($label)
                    ? \Illuminate\Support\Str::snake((string) $label)
                    : (string) $optValue;

                $optionLabel = is_array($label)
                    ? (string) ($label['label'] ?? $label['text'] ?? $optionValue)
                    : (string) $label;

                if (is_array($label) && isset($label['value'])) {
                    $optionValue = (string) $label['value'];
                }

                $normalisedValue = is_array($value)
                    ? array_map('strval', $value)
                    : $value;

                $checked = $allowsMultiple
                    ? (is_array($normalisedValue) && in_array($optionValue, $normalisedValue, true))
                    : ($normalisedValue !== null && (string) $normalisedValue === $optionValue);
            @endphp
            <input 
                id="{{ $id }}_{{ $optionValue }}" 
                {!! $field->filterAttributes($attributes, ['data-field-type', 'required', 'aria-required', 'multiple']) !!} 
                name="{{ $fieldName }}"
                value="{{ $optionValue }}"
                data-option-value="{{ $optionValue }}"
                @checked($checked) 
            />
            <label for="{{ $id }}_{{ $optionValue }}">{{ $optionLabel }}</label>
        </div>
    @endforeach
</fieldset>