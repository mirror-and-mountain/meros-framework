<?php 

use MM\Meros\App\Facades\Admin;

return [
    'settings_pages' => [
        'theme_settings' => [
            'area'       => 'theme',
            'page_title' => 'Theme Settings',
            'menu_title' => 'Settings',
            'menu_slug'  => 'theme_settings',
            'tabs'       => ['blocks', 'scripts_and_styles', 'miscellaneous'],
            'capability' => 'manage_options',
            'callback'   => [Admin::class, 'renderSettingsPage'],
        ],
        'theme_features' => [
            'area'       => 'options',
            'page_title' => 'Theme Features',
            'menu_title' => 'Features',
            'menu_slug'  => 'theme_features',
            'tabs'       => ['features', 'experimental_features'],
            'capability' => 'manage_options',
            'callback'   => [Admin::class, 'renderSettingsPage'],
        ]
    ]
];