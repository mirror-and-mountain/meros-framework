@php
    $helpText         = $showHelp ? $field->getHelpText() : '';
    $hasHelpText      = !empty($helpText);
    $helpTextPosition = $hasHelpText ? $field->getHelpTextPosition() : null;
@endphp

@if ($showLabel)
    <label for="{{ $field->getId() }}">{{ $field->getLabel() }}</label>
@endif

@if($showHelp && $helpTextPosition === 'top')
    <small class="description">{{ $helpText }}</small>
@endif

@include($view)

@if($showHelp && $helpTextPosition === 'bottom')
    <small class="description">{{ $helpText }}</small>
@endif