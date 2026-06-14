@php 
    $helpText    = $field->getHelpText();
    $hasHelpText = !empty($helpText);
@endphp

@if($hasHelpText)
    <small class="description">{{ $helpText }}</small>
@endif

@include($view)
