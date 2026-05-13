@php 
    $fieldValue = $field->getValue();
    $isSubField = $field->isSubField();
    $name = $field->getName(!$isSubField);
    $options = $field->getOptions();
    $classList = trim('meros-select-field ' . $field->classList());
    $isAdvanced = $field->isAdvanced();
@endphp

<select
    name="{{ $field->allowsMultiple() ? $name . '[]' : $name }}"
    class="{{ $classList }}"
    {!! $field->attributes(['name', 'class']) !!}
    @if($isAdvanced)
        wire:ignore
    @endif
>
    @if(empty($options))
        <option value="" disabled @selected(true)>No options configured</option>
    @endif

    @foreach($options as $value => $label)
        <option 
            value="{{ $value }}"
            @selected(
                $field->allowsMultiple()
                    ? (is_array($fieldValue) && in_array($value, $fieldValue))
                    : ($fieldValue == $value)
            )
        >
            {{ $label }}
        </option>
    @endforeach
</select>
