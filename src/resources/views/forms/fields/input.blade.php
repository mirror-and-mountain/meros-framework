@php 
    $placeholder = $field->getPlaceholder();
@endphp
<input 
    {!! $field->attributes() !!}
    @if ($placeholder && $placeholder !== '')
        placeholder="{{ $placeholder }}"
    @endif
    value="{{ $field->getValue() }}"
>