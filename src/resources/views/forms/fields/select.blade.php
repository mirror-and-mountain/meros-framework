
<select
    x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
    id="{{ $id }}"
    name="{{ $name }}"
    {!! $attributes !!}
    @change="onChange()"
>
    @foreach($options as $optValue => $label)
        @php
            $optValue = (string)$optValue;
        @endphp
        <option 
            value="{{ $optValue }}" 
            @disabled($optValue === 'placeholder')
            @selected((string)$value === (string)$optValue)>{{ $label }}
        </option>
    @endforeach
</select>