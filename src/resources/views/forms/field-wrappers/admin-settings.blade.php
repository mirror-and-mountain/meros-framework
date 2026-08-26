@php
    $inRepeater = $repeaterId !== null;
@endphp

<div class="meros-field-wrapper nice-form-group">
    @include($view)
    @if (!$inRepeater && $description !== '')
        <div style="margin-top: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif
</div>