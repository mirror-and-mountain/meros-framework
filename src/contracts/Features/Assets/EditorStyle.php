<?php

namespace MM\Meros\Contracts\Features\Assets;

class EditorStyle extends Style {
    /**
     * The area of the site where the asset should be loaded. 
     * Can be either 'site,' 'admin' or 'editor', or an array of these areas for multiple contexts.
     *
     * @var string|array<string>
     */
    final protected string|array $area = 'editor';
}