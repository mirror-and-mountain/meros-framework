@php
    $name = $allowsMultiple === true ? $name . '[]' : $name;
    $resolveDynamicValues = $resolveDynamicValues ?? true;
    $defaultValues = is_array($defaultValue)
        ? array_map(static function ($value) use ($resolveDynamicValues): string {
            if ($resolveDynamicValues && is_string($value) && Str::startsWith($value, '{{') && Str::endsWith($value, '}}')) {
                $value = \MM\Meros\Facades\Support\FormDynamicValues::resolve($value) ?? $value;
            }
            return (string) $value;
        }, $defaultValue)
        : [];
@endphp

<select
    x-data="mformsSelect"
    id="{{ $id }}"
    name="{{ $name }}"
    title="{{ $label }}"
    {!! $attributeString !!}
>
    @if(($defaultValue === null || $defaultValue === '' || $defaultValue === []) && !array_key_exists('', $options))
        <option value="" disabled @selected($allowsMultiple !== true)>Select...</option>
    @endif
    @foreach($options as $optValue => $optLabel)
        @php
            if ($resolveDynamicValues && is_string($optValue) && Str::startsWith($optValue, '{{') && Str::endsWith($optValue, '}}')) {
                $optValue = \MM\Meros\Facades\Support\FormDynamicValues::resolve($optValue) ?? $optValue;
            }
            if ($resolveDynamicValues && is_string($optLabel) && Str::startsWith($optLabel, '{{') && Str::endsWith($optLabel, '}}')) {
                $optLabel = \MM\Meros\Facades\Support\FormDynamicValues::resolve($optLabel) ?? $optLabel;
            }
            $selected = $allowsMultiple === true
                ? (is_array($defaultValue) && in_array(
                    (string) $optValue,
                    $defaultValues,
                    true
                ))
                : $defaultValue !== null && !is_array($defaultValue) && (string) $defaultValue === (string) $optValue;

            $disabled = $optValue === '';
        @endphp
        <option value="{{ $optValue }}" @disabled($disabled) @selected($selected)>{{ $optLabel }}</option>
    @endforeach
</select>