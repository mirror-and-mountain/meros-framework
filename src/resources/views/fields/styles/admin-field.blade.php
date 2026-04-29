<div class="meros-field meros-admin-field">
    @if($field->label)
        <label for="{{ $field->id }}">{{ $field->label }}</label>
    @endif

    {{-- Render the field component --}}
    <x-dynamic-component :component="$component" :field="$field" />

    @if($field->description)
        <small class="description">{{ $field->description }}</small>
    @endif
</div>