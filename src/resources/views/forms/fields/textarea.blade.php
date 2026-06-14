<textarea
    x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
    id="{{ $id }}"
    name="{{ $name }}"
    {!! $attributes !!}
    @change="onChange()"
    @input="onInput()"
>{{ $field->getValue() }}
</textarea>