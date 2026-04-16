<?php

function meros_render_field_block($attributes): string {
    $wrapper     = $attributes['wrapper'] ?? 'meros::components.fields.wrappers.site-field';
    $component   = $attributes['component'] ?? 'meros::fields.input';
    $label       = $attributes['label'] ?? 'Example Field';
    $description = $attributes['description'] ?? 'This is an example field rendered from a block.';
    $attrs       = $attributes['attrs'] ?? [];
    
    $field = [
        'label'       => $label,
        'description' => $description,
        'variation'   => $attrs['variation'] ?? 'text',
        'name'        => $attrs['name'] ?? 'example_field',
        'id'          => $attrs['id'] ?? 'example_field',
        'value'       => $attrs['value'] ?? '',
        'placeholder' => $attrs['placeholder'] ?? 'Enter a value',
    ];

    $field = (object) $field;

    if (!$wrapper) {
        return '<p>The specified wrapper could not be found.</p>';
    }

    return view($wrapper, compact('component', 'field'))->render();
}

echo meros_render_field_block($attributes);