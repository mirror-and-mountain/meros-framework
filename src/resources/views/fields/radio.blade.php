@php 
    $classList = $field->classList();;
@endphp

@foreach($field->getOptions() as $value => $label)
    <label for="{{ $field->getId() }}_{{ $value }}">
        <input 
            id="{{ $field->getId() }}_{{ $value }}"
            type="radio"
            @if(!empty($classList))
                class="{!! $classList !!}"
            @endif
            name="{{ $field->getName() }}"
            value="{{ $value }}"
            {{ $field->getValue() == $value ? 'checked' : '' }}
        >{{ $label }}
    </label>
@endforeach