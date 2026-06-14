@php 
    $allowsMultiple = $type === 'checkboxes';
    $fieldSetClass  = $isSubField ? 'nice-form-group !-mt-2.5' : 'nice-form-group';
@endphp

<fieldset id="{{ $id }}" class="{{ $fieldSetClass }}" data-field-type="{{ $fieldType }}">
    @if(!$isSubField && $label !== false)
        <legend 
            class="form-label"
        >
            {{ $label }}
            @if($isRequired)
                <span class="required-indicator">*</span>
            @endif
        </legend>
    
        @if($helpText !== false && !empty($helpText))
            <small class="description">{{ $helpText }}</small>
        @endif
    @endif

    @foreach($options as $optValue => $label)
        <div class="nice-form-group">
            @php
                $checked = $allowsMultiple
                    ? (is_array($value) && in_array($optValue, $value, true))
                    : ($value !== null && (string) $value === (string) $optValue);
            @endphp
            <input 
                id="{{ $id }}_{{ $optValue }}" 
                {!! $field->filterAttributes($attributes, ['data-field-type', 'required', 'aria-required', 'multiple']) !!} 
                name="{{ $field->getName(!$isSubField) . ($allowsMultiple ? '[]' : '') }}"
                data-option-value="{{ $optValue }}"
                @checked($checked) 
            />
            <label for="{{ $id }}_{{ $optValue }}">{{ $label }}</label>
        </div>
    @endforeach
</fieldset>