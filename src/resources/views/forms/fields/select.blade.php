
<select
    x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
    id="{{ $id }}"
    name="{{ $name }}"
    {!! $attributes !!}
    @change="onChange()"
>
    @php
        $selectedValue = is_scalar($value) ? (string) $value : '';
    @endphp

    @foreach($options as $optValue => $label)
        @php
            $optionValue = is_int($optValue) && !is_array($label)
                ? \Illuminate\Support\Str::snake((string) $label)
                : (string) $optValue;

            $optionLabel = is_array($label)
                ? (string) ($label['label'] ?? $label['text'] ?? $optionValue)
                : (string) $label;

            if (is_array($label) && isset($label['value'])) {
                $optionValue = (string) $label['value'];
            }
        @endphp
        <option 
            value="{{ $optionValue }}" 
            @disabled($optionValue === 'placeholder')
            @selected($selectedValue === $optionValue)>{{ $optionLabel }}
        </option>
    @endforeach
</select>