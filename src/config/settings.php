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
    meros_render_settings_page('meros_features', 'Features', [
        'packages' => 'Packages',
        'theme'    => 'Theme'
    ], false);
}

/**
 * Renders the Meros Theme Settings page.
 */
function meros_render_theme_settings_page() {
    meros_render_settings_page('meros_theme_settings', 'Theme Settings', [
        'blocks' => 'Blocks',
        'assets' => 'Scripts & Styles',
        'misc'   => 'Miscellaneous',
    ], true);
}

/**
 * Generic settings page renderer.
 */
function meros_render_settings_page($pageSlug, $pageTitle, $tabs, $showSubmitButton) {
    foreach ($tabs as $key => $_) {
        $hasSettings = Registry::get('settingsSections')->where('page', $pageSlug . '_' . $key)->count() > 0;
        if (!$hasSettings) {
            unset($tabs[$key]);
        }
    }

    if ($tabs === []) {
        echo "<div class=\"wrap\"><h1>{$pageTitle}</h1><p>No settings available.</p></div>";
        return;
    }

    $currentTab = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : array_key_first($tabs);

    ?>
    <div class="wrap">
        <h1><?php echo $pageTitle; ?></h1>
        <form method='post' action='options.php'>
            <nav class="nav-tab-wrapper">
                <?php
                foreach ($tabs as $slug => $label) {
                    $currentClass = $slug === $currentTab ? ' nav-tab-active' : '';
                    $url          = add_query_arg(['page' => $pageSlug, 'tab' => $slug], '');
                    echo "<a class=\"nav-tab{$currentClass}\" href=\"{$url}\">{$label}</a>";
                }
                ?>
            </nav>
            <?php
            settings_fields("{$pageSlug}_{$currentTab}");
            do_settings_sections("{$pageSlug}_{$currentTab}");
            if ($showSubmitButton) {
                submit_button();
            }
            ?>
        </form>
    </div>
    <?php
}
