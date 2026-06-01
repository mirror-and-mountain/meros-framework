@php
    $isSubField = $field->isSubField();
    $name = $field->getName(!$isSubField);
@endphp

<input type="hidden" name="{{ $name }}" value="0">

<input 
    type="checkbox"
    {!! $field->attributes() !!}
    value="1"
    {{ $field->getValue() ? 'checked' : '' }}
>