@php 
    $fieldValue = $field->getValue() ?? [];
    if (!is_array($fieldValue)) {
        $fieldValue = [$fieldValue];
    }
    $classList = $field->classList();
@endphp

@foreach($field->getOptions() as $value => $label)
    <label for="{{ $field->getId() }}_{{ $value }}">
        <input
            id="{{ $field->getId() }}_{{ $value }}"
            type="checkbox"
            @if(!empty($classList))
                class="{!! $classList !!}"
            @endif
            name="{{ $field->getName() }}[]"
            value="{{ $value }}"
            {{ is_array($fieldValue) && in_array($value, $fieldValue) ? 'checked' : '' }}
        >{{ $label }}
    </label>
@endforeach