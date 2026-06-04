<div
    class="meros-rich-textarea"
    id="{{ $field->getId() }}"
    name="{{ $field->getName() }}"
    aria-labelledby="{{ $field->getId() . '_label' }}"
    data-rt-id="{{ $field->getId() }}"
    data-field-type="{{ $field->getType() }}"
    @if($field->isDisabled()) disabled aria-disabled="true" @endif
    @if($field->isRequired()) data-required="true" aria-required="true" @endif
>
</div>