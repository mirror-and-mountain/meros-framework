<input type="hidden" name="{{ $field->name }}" value="0">

<input 
    type="checkbox"
    name="{{ $field->name }}"
    id="{{ $field->id }}"
    value="1"
    {{ $field->value ? 'checked' : '' }}
>