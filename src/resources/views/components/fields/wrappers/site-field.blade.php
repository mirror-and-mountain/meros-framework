<div class="meros-field meros-site-field nice-form-group">
    @if(isset($field->label) && $field->label)
        <label for="{{ $field->id ?? '' }}">{{ $field->label }}</label>
    @endif

    {{-- Render the field component --}}
    @if(is_string($component) && str_contains($component, '::'))
        @include($component, ['field' => $field])
    @else
        <x-dynamic-component :component="$component" :field="$field" />
    @endif

    @if(isset($field->description) && $field->description)
        <small class="description">{{ $field->description }}</small>
    @endif
</div>