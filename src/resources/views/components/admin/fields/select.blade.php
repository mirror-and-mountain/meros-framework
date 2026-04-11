<select 
    name="{{ $field->name }}{{ $field->multiSelect ? '[]' : '' }}"
    id="{{ $field->id }}"
    {{ $field->multiSelect ? 'multiple' : '' }}
>
    @foreach($field->options as $value => $label)
        <option 
            value="{{ $value }}"
            @selected(
                $field->multiSelect
                    ? (is_array($field->value) && in_array($value, $field->value))
                    : ($field->value == $value)
            )
        >
            {{ $label }}
        </option>
    @endforeach
</select>