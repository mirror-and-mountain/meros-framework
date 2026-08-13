<input type="hidden" name="{{ $name }}" value="0">
<input
    id="{{ $id }}"
    name="{{ $name }}"
    {!! $attributeString !!}
    value="1"
    {{ $defaultValue ? 'checked' : '' }}
/>
    