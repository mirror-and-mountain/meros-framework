<input type="hidden" name="{{ $name }}" value="0">
<input
    id="{{ $id }}"
    name="{{ $name }}"
    title="{{ $label }}"
    {!! $attributeString !!}
    value="1"
    {{ $defaultValue ? 'checked' : '' }}
/>
    