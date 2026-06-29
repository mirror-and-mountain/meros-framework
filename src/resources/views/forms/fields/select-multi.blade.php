<div 
    x-data="merosTomSelectField('{{ $id }}', '{{ $serialisedRules }}')"
    class="meros-ts-wrapper" 
    :class="{'opacity-50 pointer-events-none': isInstantiating}"
>
    <select 
        id="{{ $id }}"
        name="{{ str_ends_with($name, '[]') ? $name : $name . '[]' }}"
        {!! $attributes !!}
    >
        @foreach($options as $optValue => $label)
            @php
                $optValue = (string)$optValue;
            @endphp
            <option 
                value="{{ $optValue }}" 
                @disabled($optValue === 'placeholder')
                @selected(in_array($optValue, (array) $value, true))>{{ $label }}
            </option>
        @endforeach
    </select>
</div>