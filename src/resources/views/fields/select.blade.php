@php 
    $fieldValue = $field->getValue();
    $isSubField = $field->isSubField();
    $name = $field->getName(!$isSubField);
@endphp

<select
    name="{{ $field->allowsMultiple() ? $name . '[]' : $name }}"
    {!! $field->attributes(['name']) !!}
>
    @foreach($field->getOptions() as $value => $label)
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