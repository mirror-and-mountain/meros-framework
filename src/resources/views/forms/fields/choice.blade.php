@php
    $inRepeater = $repeaterId !== null;
    $inputType = $type === 'checkboxes' ? 'checkbox' : 'radio';
    $inputName = $allowsMultiple === true ? $name . '[]' : $name;
@endphp

<fieldset
    id="{{ $id }}"
    class="meros-choice-field nice-form-group"
    data-name="{{ $name }}"
    {!! $attributeString !!}
>
    @if(!$inRepeater && $renderContext !== 'settings')
        <legend class="meros-choice-field-legend">{{ $label }}</legend>
    @endif
    @if (!$inRepeater && $renderContext !== 'settings' && $description !== '')
        <div style="margin-top: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif

    @foreach($options as $optValue => $optLabel)
        @php
            $checked = $allowsMultiple === true
                ? (is_array($defaultValue) && in_array($optValue, $defaultValue, true))
                : $defaultValue !== null && (string) $defaultValue === $optValue;

            
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