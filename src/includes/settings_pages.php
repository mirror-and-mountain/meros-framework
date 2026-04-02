<?php

if (! defined('ABSPATH')) {
    exit;
}

use MM\Meros\App\Facades\Registry;

/**
 * Renders the Meros Database page.
 *
 * @return void
 */
function meros_admin_render_database_page(): void {
    echo "<div class=\"wrap\"><h1>Database</h1><p>Hello World.</p></div>";
}

/**
 * Renders the Meros Features page.
 * 
 * @return void
 */
function meros_admin_render_features_page(): void {
    meros_admin_render_settings_page('meros_features', 'Features', [
        'packages' => 'Packages',
        'theme'    => 'Theme'
    ], false);
}

/**
 * Renders the Meros Theme Settings page.
 *
 * @return void
 */
function meros_admin_render_theme_settings_page(): void {
    meros_admin_render_settings_page('meros_theme_settings', 'Theme Settings', [
        'blocks' => 'Blocks',
        'assets' => 'Scripts & Styles',
        'misc'   => 'Miscellaneous',
    ], true);
}

/**
 * Generic settings page renderer.
 * 
 * @param string $pageSlug The slug of the settings page, used for identifying which settings sections to display.
 * @param string $pageTitle The title of the settings page, displayed at the top of the page.
 * @param array  $tabs An associative array of tab slugs and their corresponding labels.
 * @param bool   $showSubmitButton Whether to show the submit button on the settings page.
 */
function meros_admin_render_settings_page($pageSlug, $pageTitle, $tabs, $showSubmitButton) {
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