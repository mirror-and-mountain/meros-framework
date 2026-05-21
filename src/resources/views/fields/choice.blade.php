@php 
    $id         = $field->getId();
    $classList  = $field->classList();
    $isSubField = $field->isSubField();
    $fieldValue = $field->getValue();
@endphp

<fieldset class="nice-form-group">
    @foreach($field->getOptions() as $value => $label)
        <div class="nice-form-group">
            @php
                $checked = $field->allowsMultiple()
                    ? (is_array($fieldValue) && in_array($value, $fieldValue, true))
                    : ($fieldValue !== null && (string) $fieldValue === (string) $value);
            @endphp
            <input 
                id="{{ $id }}_{{ $value }}" 
                {!! $field->attributes(['id', 'name']) !!} 
                name="{{ $field->getName(!$isSubField) . ($field->allowsMultiple() ? '[]' : '') }}" 
                @checked($checked) 
            />
            <label for="{{ $id }}_{{ $value }}">{{ $label }}</label>
        </div>
    @endforeach
</fieldset>