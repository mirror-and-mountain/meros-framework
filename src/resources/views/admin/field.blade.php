<div class="meros-field">
    @if($field->label)
        <label for="{{ $field->id }}">{{ $field->label }}</label>
    @endif

    {{-- Render actual field --}}
    <x-dynamic-component :component="$component" :field="$field" />

    @if($field->description)
        <p class="description">{{ $field->description }}</p>
    @endif
</div>