<?php

function meros_render_view_block($attributes) {
    $view = $attributes['view'] ?? null;
    $data = $attributes['data'] ?? [];

    if (!$view) {
        echo '<p>The specified view could not be found.</p>';
    }

    echo view($view, compact('data'));
}

meros_render_view_block($attributes);