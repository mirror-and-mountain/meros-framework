<div 
    x-data="merosTomSelectField('{{ $id }}', '{{ $serialisedRules }}')"
    class="meros-ts-wrapper" 
    :class="{'opacity-50 pointer-events-none': isInstantiating}"
>
    <select 
        id="{{ $id }}"
        name="{{ $name }}"
        {!! $attributes !!}
    >
        @foreach($options as $optValue => $label)
            @php
                $optValue = (string)$optValue;
            @endphp
            <option 
                value="{{ $optValue }}" 
                @disabled($optValue === 'placeholder')
                @selected((string)$field->getValue() === (string)$optValue)>{{ $label }}
            </option>
        @endforeach
    </select>
</div>