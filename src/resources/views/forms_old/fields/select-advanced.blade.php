<div 
    x-data="merosTomSelectField('{{ $id }}', '{{ $serialisedRules }}')"
    class="meros-ts-wrapper" 
    :class="{'opacity-50 pointer-events-none': isInstantiating}"
>
    @php
        $fieldValue = $field->getValue();
        $selectedValue = is_scalar($fieldValue) ? (string) $fieldValue : '';
    @endphp

    <select 
        id="{{ $id }}"
        name="{{ $name }}"
        {!! $attributes !!}
    >
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
</div>