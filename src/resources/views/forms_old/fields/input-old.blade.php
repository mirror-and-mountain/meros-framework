@php
    $type = $field->getType();
    $inputValue = $field->getValue();

    if (is_array($inputValue) || is_object($inputValue)) {
        $inputValue = json_encode($inputValue);
    }

    if (!is_string($inputValue) && !is_numeric($inputValue)) {
        $inputValue = $inputValue === null ? '' : (string) $inputValue;
    }
@endphp

@if ($type === 'password')
    <input
        x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
        id="{{ $id }}"
        name="{{ $name }}"
        {!! $attributes !!}
        value="{{ $inputValue }}"
        @change="onChange($event)"
        @input="onInput($event)"
        style="padding-right: 2.5rem;"
    />
    <button
        x-data="merosPasswordField($el.previousElementSibling)"
        type="button"
        x-on:click="togglePasswordVisibility()"
        style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); padding: 0.25rem 0.5rem; border: none; background: none; cursor: pointer;"
    >
        <span x-html="isPasswordVisible ? hideIcon : showIcon" :title="isPasswordVisible ? 'Hide password' : 'Show password'"></span>
    </button>
@else
    <input
        x-data="merosInputField('{{ $id }}', '{{ $serialisedRules }}')"
        id="{{ $id }}"
        name="{{ $name }}"
        {!! $attributes !!}
        value="{{ $inputValue }}"
        @change="onChange($event)"
        @input="onInput($event)"
    />
@endif