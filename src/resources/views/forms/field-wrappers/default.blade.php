@php
    $inRepeater = $repeaterId !== null;
@endphp

<div 
    class="meros-field-wrapper mforms-field-wrapper nice-form-group" 
    data-field-conditions="{{ $conditionsString }}"
    data-field-origin-name="{{ $originalName }}"
> 
    @if(!$inRepeater && $type !== 'hidden')
        <label for="{{ $id }}" class="meros-field-label">{{ $label }}</label>
    @endif
    @if (!$inRepeater && $description !== '')
        <div style="margin-bottom: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif
    @include($view)
</div>