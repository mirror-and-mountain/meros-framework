<div 
    x-data="merosTomSelectField('{{ $id }}', '{{ $serialisedRules }}')"
    class="meros-ts-wrapper" 
    :class="{'opacity-50 pointer-events-none': isInstantiating}"
>
    @php
        $classList = ' ' . $field->classList() . ' ';
        $isAdminField = str_contains($classList, ' meros-admin-field ');
        $selectName = str_ends_with($name, '[]') ? $name : $name . '[]';
        $markerName = \MM\Meros\Support\FormFieldName::emptyMarkerName($selectName);
        $selectedValues = array_map('strval', (array) $value);
    @endphp

    @if($isAdminField && is_string($markerName))
        <input type="hidden" name="{{ $markerName }}" value="1" data-multi-select-empty-value="true">
    @endif

    <select 
        id="{{ $id }}"
        name="{{ $selectName }}"
        {!! $attributes !!}
    >
        @foreach($options as $optValue => $label)
            @php
                $optValue = (string)$optValue;
            @endphp
            <option 
                value="{{ $optValue }}" 
                @disabled($optValue === 'placeholder')
                @selected(in_array($optValue, $selectedValues, true))>{{ $label }}
            </option>
        @endforeach
    </select>
</div>