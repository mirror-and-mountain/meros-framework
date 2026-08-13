@php
    $properties = $properties ?? null;

    if ($properties === null) {
        $field = $field ?? null;

        if ($field !== null && is_array($field) && isset($field['properties'])) {
            $properties = $field['properties'];
        }
    }

    $view = $properties !== null && isset($properties['view']) ? $properties['view'] : null;
@endphp

<div class="meros-field-wrapper">
    @include($view, $properties ?? [])
</div>