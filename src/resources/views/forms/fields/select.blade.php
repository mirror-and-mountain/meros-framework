@php
    $name = $allowsMultiple === true ? $name . '[]' : $name;
@endphp

<select
    x-data="mformsSelect"
    id="{{ $id }}"
    name="{{ $name }}"
    title="{{ $label }}"
    {!! $attributeString !!}
>

    @foreach($options as $optValue => $optLabel)
        @php
            $selected = $allowsMultiple === true
                ? (is_array($defaultValue) && in_array($optValue, $defaultValue, true))
                : $defaultValue !== null && (string) $defaultValue === $optValue;
        @endphp
        <option value="{{ $optValue }}" @selected($selected)>{{ $optLabel }}</option>
    @endforeach
</select>