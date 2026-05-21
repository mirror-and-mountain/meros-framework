@php
    $label = $field->getLabel();
    $helpText = $field->getHelpText();
@endphp


<div class="meros-field meros-admin-field">
    @if(!empty($label))
        <label for="{{ $field->getId() }}">{{ $label }}</label>
    @endif

    @include($view)

    @if(!empty($helpText))
        <small class="description">{{ $helpText }}</small>
    @endif
</div>