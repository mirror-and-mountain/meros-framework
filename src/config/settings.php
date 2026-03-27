<?php

use MM\Meros\App\Facades\Registry;

return [
    'pages' => [
        [
            'page_title' => 'Theme Settings',
            'menu_title' => 'Settings',
            'menu_slug'  => 'meros_theme_settings',
            'position'   => 2,
            'area'       => 'theme',
            'callback'   => 'meros_render_theme_settings_page',
        ],

        [
            'page_title' => 'Features',
            'menu_title' => 'Features',
            'menu_slug'  => 'meros_features',
            'callback'   => 'meros_render_features_page',
            'position'   => 1,
            'ajax'       => true,
        ]
    ]
];

/**
 * Renders the Meros Features page.
 */
function meros_render_features_page() {
    $tabs = [
        'packages' => 'Packages',
        'theme'    => 'Theme'
    ];

    $currentTab     = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : array_key_first($tabs);
    $tabHasSettings = Registry::get('settingsSections')->where('page', 'meros_features_' . $currentTab)->count() > 0;

    ?>
    <div class="wrap">
        <h1>Features</h1>
        <form method='post' action='options.php'>
            <nav class="nav-tab-wrapper">
                <?php
                foreach ($tabs as $slug => $label) {
                    if (!$tabHasSettings) {
                        continue; // Don't show the tab if it has no registered settings sections
                    }

                    $currentClass = $slug === $currentTab ? ' nav-tab-active' : '';
                    $url          = add_query_arg(['page' => 'meros_features', 'tab' => $slug], '');
                    
                    echo "<a class=\"nav-tab{$currentClass}\" href=\"{$url}\">{$label}</a>";
                }
                ?>
            </nav>
            <?php
            if (!$tabHasSettings) {
                echo '<p>No settings available for this tab.</p>';
            } else {
                settings_fields("meros_features_{$currentTab}");
                do_settings_sections("meros_features_{$currentTab}");
                // No submit button as we use AJAX to save settings...
            }
            ?>
        </form>
    </div>
    <?php
}

/**
 * Renders the Meros Theme Settings page.
 */
function meros_render_theme_settings_page() {
    $tabs = [
        'blocks' => 'Blocks',
        'assets' => 'Scripts & Styles',
        'misc'   => 'Miscellaneous',
    ];

    $currentTab     = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : array_key_first($tabs);
    $tabHasSettings = Registry::get('settingsSections')->where('page', 'meros_theme_settings_' . $currentTab)->count() > 0;

    ?>
    <div class="wrap">
        <h1>Theme Settings</h1>
        <form method='post' action='options.php'>
            <nav class="nav-tab-wrapper">
                <?php
                foreach ($tabs as $slug => $label) {
                    if (!$tabHasSettings) {
                        continue; // Don't show the tab if it has no registered settings sections
                    }

                    $currentClass = $slug === $currentTab ? ' nav-tab-active' : '';
                    $url          = add_query_arg(['page' => 'meros_theme_settings', 'tab' => $slug], '');
                    
                    echo "<a class=\"nav-tab{$currentClass}\" href=\"{$url}\">{$label}</a>";
                }
                ?>
            </nav>
            <?php
            if (!$tabHasSettings) {
                echo '<p>No settings available for this tab.</p>';
            } else {
                settings_fields("meros_theme_settings_{$currentTab}");
                do_settings_sections("meros_theme_settings_{$currentTab}");
                submit_button();
            }
            ?>
        </form>
    </div>
    <?php
}