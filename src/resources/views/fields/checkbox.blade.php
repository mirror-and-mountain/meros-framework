<input type="hidden" name="{{ $field->getName() }}" value="0">

<input 
    type="checkbox"
    {!! $field->attributes() !!}
    value="1"
    {{ $field->getValue() ? 'checked' : '' }}
>