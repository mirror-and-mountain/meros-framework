@php
    $inputValue = $field->getValue();

    if (is_array($inputValue) || is_object($inputValue)) {
        $inputValue = json_encode($inputValue);
    }

    if (!is_string($inputValue) && !is_numeric($inputValue)) {
        $inputValue = $inputValue === null ? '' : (string) $inputValue;
    }
@endphp

<input
    x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
    id="{{ $id }}"
    name="{{ $name }}"
    {!! $attributes !!}
    value="{{ $inputValue }}"
    @change="onChange()"
    @input="onInput()"
/>