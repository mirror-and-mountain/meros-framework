@php
    $properties = $properties ?? null;

    if ($properties === null) {
        $field = $field ?? null;

        if ($field !== null && is_array($field) && isset($field['properties'])) {
            $properties = $field['properties'];
        }
    }

    $view = $properties !== null && isset($properties['view']) ? $properties['view'] : null;
    $description = $properties !== null && (isset($properties['description']) && $properties['description'] !== '' ) ? $properties['description'] : null;
@endphp

<div class="meros-field-wrapper">
    @include($view, $properties ?? [])
    @if ($description !== null)
        <div style="margin-top: 0.5rem;">
            <small class="meros-field-description">{{ $description }}</small>
        </div>
    @endif
</div>