@php
    $inRepeater = $repeaterId !== null;
@endphp

<div class="meros-field-wrapper nice-form-group">
    @if(!$inRepeater)
        <label for="{{ $id }}" class="meros-field-label">{{ $label }}</label>
    @endif
    @include($view)
    @if (!$inRepeater && $description !== '')
        <div style="margin-top: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif
</div>