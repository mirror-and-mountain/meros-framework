@php
    $id = $field->getId();
@endphp
<div
    x-data="quill"
    class="meros-rich-textarea"
    id="{{ $id }}"
    name="{{ $field->getName() }}"
    aria-labelledby="{{ $id . '-label' }}"
    data-rt-id="{{ $id }}"
    data-field-type="{{ $field->getType() }}"
    @if($field->isDisabled()) disabled aria-disabled="true" @endif
    @if($field->isRequired()) data-required="true" aria-required="true" @endif
>
</div>