<?php

if (! defined('ABSPATH')) {
    exit;
}

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use MM\Meros\App\Facades\Registry;
use MM\Meros\App\Support\Admin\SchemaManager;

/**
 * Renders the Meros Database page.
 *
 * @return void
 */
function meros_admin_render_database_page(): void {
    $viewingTable = isset($_GET['table']) 
        ? sanitize_text_field($_GET['table']) 
        : null;

    if ($viewingTable) {
        echo 'Testing';
    }

    else {
        $tables = SchemaManager::getTables();
        $data   = $tables->map(function ($table) {
            $tableData = SchemaManager::getTableData($table['name']);

            return [
                'name'        => $table['name'],
                'source'      => $table['source'],
                'columns'     => count($tableData['columns'] ?? []),
                'primary_key' => collect($tableData['indexes'] ?? [])->firstWhere('primary', true)['columns'][0] ?? 'N/A',
            ];
        });

        $columns = ['name', 'source', 'columns', 'primary_key'];
        $args    = [
            'default_order_by' => 'source',
            'sortable'         => true,
            'selectable'       => true,
            'action_column'    => 'name',
            'actions'          => [
                'view' => function ($row) {
                    $url = admin_url('admin.php?page=meros_database&table=' . urlencode($row['name']));
                    return '<a href="' . esc_url($url) . '">View | </a>';
                },
                'edit' => function ($row) {
                    $url = admin_url('admin.php?page=meros_database&table=' . urlencode($row['name']) . '&action=edit');
                    return '<a href="' . esc_url($url) . '">Edit</a>';
                },
            ],
        ];

        $content = '<p>Below is a list of all database tables associated with this WordPress installation, including those created by plugins and themes. This information can be useful for debugging, optimization, or when performing manual database operations. Please exercise caution when interacting with the database, as changes can affect the functionality of your site.</p>';
        meros_admin_render_table_page(
            'meros_database',
            'Database Tables',
            $columns,
            $data,
            $content,
            $args
        );
    }
}

/**
 * Renders the Meros Features page.
 * 
 * @return void
 */
function meros_admin_render_features_page(): void {
    meros_admin_render_tabbed_page('meros_features', 'Features', [
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
    meros_admin_render_tabbed_page('meros_theme_settings', 'Theme Settings', [
        'blocks' => 'Blocks',
        'assets' => 'Scripts & Styles',
        'misc'   => 'Miscellaneous',
    ], true);
}

/**
 * Renders a tabbed settings page in the WordPress admin.
 * 
 * @param string $pageSlug The slug of the settings page, used for identifying which settings sections to display.
 * @param string $pageTitle The title of the settings page, displayed at the top of the page.
 * @param array  $tabs An associative array of tab slugs and their corresponding labels.
 * @param bool   $showSubmitButton Whether to show the submit button on the settings page.
 */
function meros_admin_render_tabbed_page($pageSlug, $pageTitle, $tabs, $showSubmitButton) {
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

/**
 * Renders a table page in the WordPress admin.
 *
 * @param string     $slug The slug of the page, used for URL generation and identifying the page.
 * @param string     $title The title of the page, displayed at the top of the page.
 * @param array      $columns An array of column names to display in the table.
 * @param Collection $data A collection of data to populate the table rows.
 * @param string     $content Optional content to display above the table.
 * @param array      $args Optional arguments for configuring the table behavior (e.g., sortable, selectable).
 */
function meros_admin_render_table_page(
    string     $slug,
    string     $title,
    array      $columns, 
    Collection $data,
    string     $content = '',
    array      $args = []
): void {

    $sortable       = $args['sortable'] ?? true;
    $selectable     = $args['selectable'] ?? false;
    $defaultOrderBy = $args['default_order_by'] ?? null;
    $actionColumn   = $args['action_column'] ?? null;
    $actions        = $args['actions'] ?? [];

    if ($sortable) {
        $currentOrder = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc';
        $ariaSort     = $currentOrder === 'desc' ? 'descending' : 'ascending';
        $sortClass    = $currentOrder === 'desc' ? 'desc' : 'asc';
        $orderBy      = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : $defaultOrderBy;
    } else {
        $currentOrder = null;
        $ariaSort     = null;
        $sortClass    = null;
        $orderBy      = null;
    }

    $getColumnHeader = 
        function (string $columnName) use ($sortable, $currentOrder, $sortClass, $ariaSort, $slug) {
            $id  = Str::snake($columnName);
            $url = esc_url(add_query_arg([
                'orderby' => $id, 
                'order'   => $currentOrder === 'asc' ? 'desc' : 'asc', 
                'page'    => $slug
            ]));

            return $sortable 
                ?
                
                '<th scope="col" id="' . esc_attr($id) . '" class="manage-column column-title column-primary sorted ' . esc_attr($sortClass) . '" aria-sort="' . esc_attr($ariaSort) . '" abbr="' . esc_attr($columnName) . '">
                    <a href="' . $url . '">
                        <span>' . esc_html(Str::headline($columnName)) . '</span>
                        <span class="sorting-indicators">
                            <span class="sorting-indicator asc" aria-hidden="true"></span>
                            <span class="sorting-indicator desc" aria-hidden="true"></span>
                        </span>
                    </a>
                </th>'

                : 
                
                '<th scope="col" id="' . esc_attr($id) . '" class="manage-column column-title column-primary" abbr="' . esc_attr($columnName) . '">
                    <span>' . esc_html($columnName) . '</span>
                </th>';
        };

    $data = $currentOrder && $orderBy ? $data->sortBy($orderBy, SORT_REGULAR, $currentOrder === 'desc') : $data;

    ?>
    <div class="wrap">
        <h1><?php echo esc_html($title); ?></h1>
        <?php echo wp_kses_post($content); ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php if ($selectable): ?>
                        <td scope="col" class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1" />
                            <label for="cb-select-all-1">
                                <span class="screen-reader-text">Select All</span>
                            </label>
                        </td>
                    <?php endif; ?>
                    <?php foreach ($columns as $column): ?>
                        <?php echo $getColumnHeader($column); ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php $i = 2; foreach ($data as $row): ?>
                    <tr>
                        <?php if ($selectable): ?>
                            <td scrope="row" class="check-column" style="padding:11px;">
                                <input
                                    id="cb-select-<?php echo esc_attr($i) ?>"
                                    type="checkbox" 
                                    value="<?php echo esc_attr($i); ?>"
                                />
                                <label for="cb-select-<?php echo esc_attr($i); ?>">
                                    <span class="screen-reader-text">Select <?php echo esc_html($row['name']); ?></span>
                                </label>
                            </td>
                        <?php endif; ?>
                        <?php foreach ($columns as $column): $isFirst = $column === reset($columns); ?>
                            <td 
                                <?php echo $isFirst ? 'class="column-primary title"' : ''; ?>
                                data-colname="<?php echo esc_attr($column); ?>"
                            >
                                <?php echo esc_html($row[ $column ] ?? ''); ?>
                                <?php if ($column === $actionColumn && $actions !== []): ?>
                                    <div class="">
                                        <?php foreach ($actions as $actionKey => $actionCallback): ?>
                                            <?php if (! is_callable($actionCallback)) continue; ?>
                                            <span class="<?php echo esc_attr($actionKey); ?>">
                                                <?php echo wp_kses_post($actionCallback($row)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}