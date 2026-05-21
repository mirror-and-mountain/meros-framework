@php 
    $helpText    = $field->getHelpText();
    $hasHelpText = !empty($helpText);
    $helpTextPosition = $hasHelpText ? $field->getHelpTextPosition() : null;
@endphp

@if($helpTextPosition === 'top')
    <small class="description">{{ $helpText }}</small>
@endif

@include($view)

@if($helpTextPosition === 'bottom')
    <small class="description">{{ $helpText }}</small>
@endif
