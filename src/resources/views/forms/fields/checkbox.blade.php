@php
    $isSubField = $field->isInRepeater();
    $name = $field->getName(!$isSubField);
@endphp

<input type="hidden" name="{{ $name }}" value="0">

<input
    id="{{ $id }}"
    name="{{ $name }}"
    type="checkbox"
    value="1"
    {!! $attributes !!}
    {{ $field->getValue() ? 'checked' : '' }}
>