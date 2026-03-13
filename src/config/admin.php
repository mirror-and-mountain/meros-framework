<?php 

use MM\Meros\App\Services\Theme\AdminManager;

return [
    'options_pages' => [
        'theme_settings' => [
            'page_title' => 'Theme Settings',
            'menu_title' => 'Settings',
            'menu_slug'  => 'theme_settings',
            'tabs'       => ['blocks', 'scripts_and_styles', 'miscellaneous'],
            'capability' => 'manage_options',
            'callback'   => [AdminManager::class, 'renderOptionsPage'],
        ],
        'theme_features' => [
            'page_title' => 'Theme Features',
            'menu_title' => 'Features',
            'menu_slug'  => 'theme_features',
            'tabs'       => ['features', 'experimental_features'],
            'capability' => 'manage_options',
            'callback'   => [AdminManager::class, 'renderOptionsPage'],
        ]
    ]
];