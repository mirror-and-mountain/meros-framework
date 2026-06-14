<input
    x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
    id="{{ $id }}"
    name="{{ $name }}"
    {!! $attributes !!}
    value="{{ $field->getValue()}}"
    @change="onChange()"
    @input="onInput()"
/>