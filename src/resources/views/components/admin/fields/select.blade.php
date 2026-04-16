<select 
    name="{{ $field->name }}{{ $field->multiple ? '[]' : '' }}"
    id="{{ $field->id }}"
    class="meros-select {{ implode(' ', $field->classList) }}"
    {{ $field->multiple ? 'multiple' : '' }}
>
    @foreach($field->options as $value => $label)
        @php
            // If options are not key-value pairs, treat the value as the label
            if (is_int($value)) {
                $value = $label;
            }
        @endphp
        <option 
            value="{{ $value }}"
            @selected(
                $field->multiple
                    ? (is_array($field->value) && in_array($value, $field->value))
                    : ($field->value == $value)
            )
        >
            {{ $label }}
        </option>
    @endforeach
</select>