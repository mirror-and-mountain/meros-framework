@php 
    $fieldValue = $field->getValue();
    $isSubField = $field->isSubField();
    $name       = $field->getName(!$isSubField);
    $options    = $field->getOptions();
    $classList  = trim('meros-select-field ' . $field->classList());
    $isAdvanced = $field->isAdvanced();
    $allowsAdd  = $field->allowsAdd();
    $onChange   = $field->getOnChange();
@endphp

<select
    name="{{ $field->allowsMultiple() ? $name . '[]' : $name }}"
    class="{{ $classList }}"
    {!! $field->attributes(['name', 'class']) !!}
    data-advanced="{{ $isAdvanced ? 'true' : 'false' }}"
    data-allow-add="{{ $allowsAdd ? 'true' : 'false' }}"
    x-on:change="$store.formStore.evalFieldConditions($el); @if(!empty($onChange)) merosForms.invokeCallback(@js($onChange), $event) @endif"
>
    @if(empty($options) && !$isAdvanced)
        <option value="" disabled @selected(true)>No options configured</option>
    @endif

    @foreach($options as $value => $label)
        <option 
            value="{{ $value }}"
            @selected(
                $field->allowsMultiple()
                    ? (is_array($fieldValue) && in_array($value, $fieldValue, true))
                    : ($fieldValue !== null && (string) $fieldValue === (string) $value)
            )
        >
            {{ $label }}
        </option>
    @endforeach
</select>
