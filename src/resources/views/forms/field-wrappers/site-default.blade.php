@php
    $id               = $field->getId();
    $helpText         = $showHelp ? $field->getHelpText() : '';
    $hasHelpText      = !empty($helpText);
    $helpTextPosition = $hasHelpText ? $field->getHelpTextPosition() : null;
    $showLabel        = $showLabel;
    $isRequired       = $field->isRequired();
    $isDisabled       = $field->isDisabled();
    $isFieldSet       = in_array($field->handle, ['radio', 'checkboxes']);
    $wireIgnore       = (method_exists($field, 'isAdvanced') && $field->isAdvanced()) || $field->handle === 'rich_text' ? 'wire:ignore' : '';

    if ($isRequired && $isDisabled) {
        $isRequired = false;
    }

@endphp

<div 
    class="meros-field nice-form-group" {{ $showLabel === false ? 'style=margin-top:0;' : '' }}
    {{ $wireIgnore }}
>
    @if ($showLabel)
        @if (!$isFieldSet)
            <label 
                @if($field->handle === 'rich_text')
                    id="{{ $id }}-label"
                @else
                    id="{{ $id }}-label"
                    for="{{ $id }}"
                @endif
                class="form-label"
            >
                {{ $field->getLabel() }}
                @if($isRequired)
                    <span class="required-indicator">*</span>
                @endif
            </label>
        @endif
    @endif

    @if(!$isFieldSet && $showHelp && ($helpTextPosition === 'top' || $field->handle === 'checkbox'))
        <small class="description field-help-text-top">{{ $helpText }}</small>
    @endif

    @include($view)

    @if(!$isFieldSet && $showHelp && ($helpTextPosition === 'bottom' && $field->handle !== 'checkbox'))
        <small class="description field-help-text-bottom">{{ $helpText }}</small>
    @endif
</div>