@php
    $inRepeater = $repeaterId !== null;
@endphp

<div 
    class="meros-field-wrapper mforms-field-wrapper nice-form-group"
    data-field-conditions="{{ $conditionsString }}"
    data-field-origin-name="{{ $originalName }}"
>
    @include($view)
    @if (!$inRepeater && $description !== '')
        <div style="margin-top: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif
</div>