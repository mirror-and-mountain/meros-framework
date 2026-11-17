@php
    $inRepeater = $repeaterId !== null;
    
    if (($resolveDynamicValues ?? true) && isset($defaultValue) && is_string($defaultValue) && Str::startsWith($defaultValue, '{{') && Str::endsWith($defaultValue, '}}')) {
        $dynamicValue = \MM\Meros\Facades\Support\FormDynamicValues::resolve($defaultValue);
        if ($dynamicValue !== null) {
            $defaultValue = $dynamicValue;
        }
    }
@endphp

<div 
    class="meros-field-wrapper mforms-field-wrapper nice-form-group" 
    data-field-conditions="{{ $conditionsString }}"
    data-field-origin-name="{{ $originalName }}"
> 
    @if(!$inRepeater && $type !== 'hidden')
        <label 
            for="{{ $id }}" 
            class="meros-field-label"
        >{{ $label }}
        @if($required ?? false)
            <span class="meros-field-required">*</span>
        @endif
    </label>
    @endif
    @if (!$inRepeater && $description !== '')
        <div style="margin-bottom: 0.5rem;">
            <small class="meros-field-description">{!! $description !!}</small>
        </div>
    @endif
    @include($view)
</div>