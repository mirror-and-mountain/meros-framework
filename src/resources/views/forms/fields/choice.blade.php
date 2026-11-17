@php
    $inRepeater = $repeaterId !== null;
    $inputType = $type === 'checkboxes' ? 'checkbox' : 'radio';
    $inputName = $allowsMultiple === true ? $name . '[]' : $name;
    $resolveDynamicValues = $resolveDynamicValues ?? true;
    $defaultValues = is_array($defaultValue)
        ? array_map(static function ($value) use ($resolveDynamicValues): string {
            if ($resolveDynamicValues && is_string($value) && Str::startsWith($value, '{{') && Str::endsWith($value, '}}')) {
                $value = \MM\Meros\Facades\Support\FormDynamicValues::resolve($value) ?? $value;
            }
            return (string) $value;
        }, $defaultValue)
        : [];
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
        <legend 
            class="meros-choice-field-legend"
            >{{ $label }}
            @if($required ?? false)
                <span class="meros-field-required">*</span>
            @endif
        </legend>
    @endif
    @if (!$inRepeater && $renderContext !== 'settings' && $description !== '')
        <div style="margin-bottom: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif

    @foreach($options as $optValue => $optLabel)
        @php
            if (($resolveDynamicValues ?? true) && is_string($optValue) && Str::startsWith($optValue, '{{') && Str::endsWith($optValue, '}}')) {
                $dynamicValue = \MM\Meros\Facades\Support\FormDynamicValues::resolve($optValue);
                if ($dynamicValue !== null) {
                    $optValue = $dynamicValue;
                }
            }
            if ($resolveDynamicValues && is_string($optLabel) && Str::startsWith($optLabel, '{{') && Str::endsWith($optLabel, '}}')) {
                $optLabel = \MM\Meros\Facades\Support\FormDynamicValues::resolve($optLabel) ?? $optLabel;
            }
            $checked = $allowsMultiple === true
                ? (is_array($defaultValue) && in_array(
                    (string) $optValue,
                    $defaultValues,
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