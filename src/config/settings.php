<?php

use MM\Meros\App\Framework;

$include = trailingslashit(Framework::getPath()) . 'includes' . DIRECTORY_SEPARATOR . 'settings_pages.php';

if (file_exists($include)) {
    require_once $include;
}

return [
    'pages' => [
        [
            'page_title' => 'Theme Settings',
            'menu_title' => 'Settings',
            'menu_slug'  => 'meros_theme_settings',
            'position'   => 2,
            'area'       => 'theme',
            'callback'   => function_exists('meros_admin_render_theme_settings_page') ? 'meros_admin_render_theme_settings_page' : function() {
                echo "<div class=\"wrap\"><h1>Theme Settings</h1><p>No settings available.</p></div>";
            },
        ],

        [
            'page_title' => 'Features',
            'menu_title' => 'Features',
            'menu_slug'  => 'meros_features',
            'callback'   => function_exists('meros_admin_render_features_page') ? 'meros_admin_render_features_page' : function() {
                echo "<div class=\"wrap\"><h1>Features</h1><p>No settings available.</p></div>";
            },
            'position'   => 1,
            'ajax'       => true,
        ],

        [
            'page_title' => 'Database',
            'menu_title' => 'Database',
            'menu_slug'  => 'meros_database',
            'callback'   => function_exists('meros_admin_render_database_page') ? 'meros_admin_render_database_page' : function() {
                echo "<div class=\"wrap\"><h1>Database</h1><p>No settings available.</p></div>";
            },
            'position'   => 2,
        ]
    ]
];
