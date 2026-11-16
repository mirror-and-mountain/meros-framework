@php
    $inRepeater = $repeaterId !== null;
    $inputType = $type === 'checkboxes' ? 'checkbox' : 'radio';
    $inputName = $allowsMultiple === true ? $name . '[]' : $name;
@endphp

<fieldset
    x-data="mformsChoice"
    id="{{ $id }}"
    class="meros-choice-field meros-field-wrapper nice-form-group mforms-field-wrapper"
    data-name="{{ $name }}"
    data-field-conditions="{{ $conditionsString }}"
    data-field-origin-name="{{ $originalName }}"
    {!! $attributeString !!}
>
    @if(!$inRepeater && $renderContext !== 'settings')
        <legend class="meros-choice-field-legend">{{ $label }}</legend>
    @endif
    @if (!$inRepeater && $renderContext !== 'settings' && $description !== '')
        <div style="margin-bottom: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif

    @foreach($options as $optValue => $optLabel)
        @php
            $checked = $allowsMultiple === true
                ? (is_array($defaultValue) && in_array(
                    (string) $optValue,
                    array_map(static fn ($value): string => (string) $value, $defaultValue),
                    true
                ))
                : $defaultValue !== null && (string) $defaultValue === (string) $optValue;

            
        @endphp
        <div class="nice-form-group">
            <input
                id="{{ $id }}-{{ $optValue }}"
                class="meros-choice-field-input"
                type="{{ $inputType }}"
                name="{{ $inputName }}"
                value="{{ $optValue }}"
                @checked($checked)
            />
            <label for="{{ $id }}-{{ $optValue }}">{{ $optLabel }}</label>
        </div>
    @endforeach
</fieldset>