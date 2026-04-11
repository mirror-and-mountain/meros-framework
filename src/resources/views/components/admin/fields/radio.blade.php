@foreach($field->options as $value => $label)
    <label>
        <input 
            type="radio"
            name="{{ $field->name }}"
            value="{{ $value }}"
            {{ $field->value == $value ? 'checked' : '' }}
        >
        {{ $label }}
    </label>
@endforeach