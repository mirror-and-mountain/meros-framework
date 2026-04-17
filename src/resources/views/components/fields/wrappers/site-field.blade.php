@php
    $showLabel = $showLabel ?? true;
    $showDescription = $showDescription ?? true;
@endphp

<div class="meros-field meros-site-field nice-form-group" {{ $showLabel === false ? 'style=margin-top:0;' : '' }}>
    @if($showLabel && isset($field->label) && $field->label)
        <label for="{{ $field->id ?? '' }}">{{ $field->label }}</label>
    @endif

    {{-- Render the field component --}}
    @if(is_string($component) && str_contains($component, '::'))
        @include($component, ['field' => $field])
    @else
        <x-dynamic-component :component="$component" :field="$field" />
    @endif

    @if($showDescription && isset($field->description) && $field->description)
        <small class="description">{{ $field->description }}</small>
    @endif
</div>