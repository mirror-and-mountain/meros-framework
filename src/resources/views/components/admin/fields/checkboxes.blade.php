@foreach($field->options as $value => $label)
    <label>
        <input 
            type="checkbox"
            name="{{ $field->name }}[]"
            value="{{ $value }}"
            {{ is_array($field->value) && in_array($value, $field->value) ? 'checked' : '' }}
        >
        {{ $label }}
    </label>
@endforeach