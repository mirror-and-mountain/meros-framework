@php
    $id               = $field->getId();
    $helpText         = $showHelp ? $field->getHelpText() : '';
    $hasHelpText      = !empty($helpText);
    $helpTextPosition = $hasHelpText ? $field->getHelpTextPosition() : null;
    $showLabel        = $showLabel;
    $isFieldSet       = in_array($field->handle, ['radio', 'checkboxes']);
    $wireIgnore       = (method_exists($field, 'isAdvanced') && $field->isAdvanced()) || $field->handle === 'rich_text' ? 'wire:ignore' : '';
@endphp

<div 
    class="meros-field nice-form-group" {{ $showLabel === false ? 'style=margin-top:0;' : '' }}
    {{ $wireIgnore }}
>
    @if ($showLabel && $field->handle !== 'repeater')
        @if (!$isFieldSet)
            <label for="{{ $id }}">{{ $field->getLabel() }}</label>
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