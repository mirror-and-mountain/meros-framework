<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Features\Data\Table;
use MM\Meros\Registers\Data\Tables;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Contracts\Features\Concerns\SanitizesHtml;

use MM\Meros\App\Package;

trait ProvidesTables {
    use Abstracts, SanitizesHtml;

    /**
     * Resolves a specific table or the tables register based on the provided handle.
     *
     * @param string $handle Optional. The handle of the table to retrieve.
     *
     * @return Table|Tables|null The requested table or the tables register.
     */
    final public function tables(string $handle = ''): Table|Tables|null {
        return $this->resolveFeatureRequestFor(Table::class, $handle);
    }

    /**
     * Checks if the provider has any registered tables.
     *
     * @return boolean
     */
    final public function hasRegisteredTables(): bool {
        return $this->tables()->hasRegisteredTables();
    }

    /**
     * Returns whether the provider has any required tables that are not installed.
     *
     * @return Collection A collection of required tables that are not installed.
     */
    final protected function getUninstalledRequiredTables(): Collection {
        $tables = collect($this->tables()->init());

        return $tables->filter(function (Table $table) {
            return $table->isRequired() && !$table->isInstalled();
        });
    }

    /**
     * Initialises the table management page for the provider.
     *
     * @param Page $settingsPage The settings page to which the table management page will be added.
     *
     * @return Page The initialized table management page.
     */
    final protected function initTableManagementPage(Page $settingsPage): Page {
        $page = $settingsPage->subpageParam('tables')->subpage(function (Page $subpage) use ($settingsPage) {
            $subpage->slug($settingsPage->getSlug() . '-tables');
            $subpage->title('Manage Tables');
            $subpage->showTitle(false);

            $providerHandle = $this->getProvider()->getHandle();

            $subpage->addAjaxAction('meros_handle_table_action_' . $providerHandle, function () use ($providerHandle) {
                $isValid = $this->validateTableOperation(
                    $_POST['table'] ?? '',
                    $providerHandle,
                    $_POST['nonce'] ?? '',
                    $_POST['operation'] ?? ''
                );

                if (!$isValid) {
                    wp_send_json_error(['message' => 'Invalid table operation.']);
                }

                $table = $_POST['table'] ?? '';
                $operation = $_POST['operation'] ?? '';

                $this->handleTableOperation($table, $operation);
            });

            $subpage->callback(function () {
                $this->renderTableManagementPage();
            });
        });

        return $page;
    }

    /**
     * Renders the table management page for the provider.
     *
     * @return void
     */
    private function renderTableManagementPage(): void {
        $tables = $this->tables()->init();

        $enabled        = true;
        $provider       = $this->getProvider();
        $isPackage      = $provider instanceof Package;
        $providerHandle = $provider->getHandle();
        $providerName   = $provider->getName();

        if ($provider instanceof Package) {
            $enabled = $provider->isEnabled(true);
        }

        echo '<h1>' . esc_html($providerName) . ' Tables</h1>';

        $parentPage = $_GET['page'] ?? '';

        if ($parentPage === 'meros-packages' && isset($_GET['package'])) {
            echo '<nav class="meros-breadcrumbs" aria-label="Breadcrumb">
                    <a href="' . admin_url('admin.php?page=meros-packages') . '">Packages</a>';

                    if (($isPackage && $enabled) || !$isPackage) {
                        echo '<span class="meros-breadcrumb-separator"> / </span>';
                        echo '<a href="' . admin_url('admin.php?page=meros-packages&package=' . $_GET['package']) . '">' . esc_html($providerName) . ' Settings</a>';
                    }

                    echo '<span class="meros-breadcrumb-separator"> /</span>
                    <span>Tables</span>
                </nav>';
        }

        if ($parentPage === 'meros-theme-settings') {
            echo '<a href="' . admin_url('admin.php?page=meros-theme-settings') . '" class="meros-back-button">
                    &larr; Back to Theme Settings
                </a>';
        }

        echo '<p>Custom tables can be provided by the active theme or by packages. They may be optional or required depending on what they are used for.<br>';

        if ($isPackage) {
            echo '<span>Required tables don\'t offer options to install or uninstall them here if the package is enabled, but can be uninstalled when the package is deactivated.</span>';
        } else {
            echo '<span>Required tables may not offer options to install or uninstall them here, but can be uninstalled when the theme is deactivated.</span>';
        }

        echo '</p>';

        $installAllButton   = $this->getTablesMultiActionButton($tables, $providerHandle, 'install', $isPackage);
        $updateAllButton    = $this->getTablesMultiActionButton($tables, $providerHandle, 'update', $isPackage);
        $uninstallAllButton = $this->getTablesMultiActionButton($tables, $providerHandle, 'uninstall', $isPackage);

        if ($installAllButton !== '' || $updateAllButton !== '' || $uninstallAllButton !== '') {
            echo '<div class="meros-tables-actions">';
        }

        if (($isPackage && $enabled) || !$isPackage) {
            if ($installAllButton !== '') {
                echo $installAllButton;
            }

            if ($updateAllButton !== '') {
                echo $updateAllButton;
            }
        }

        if (($isPackage && !$enabled) || !$isPackage) {
            if ($uninstallAllButton !== '') {
                echo $uninstallAllButton;
            }
        }

        if ($installAllButton !== '' || $updateAllButton !== '' || $uninstallAllButton !== '') {
            echo '</div>';
        }

        echo '<div class="meros-table-wrapper">';
        
        foreach ($tables as $table) {
            $name        = $table->getName();
            $required    = $table->isRequired();
            $installed   = $table->isInstalled();
            $canInstall  = $installed ? false : $table->canInstall();
            $installedAt = $installed ? $table->getInstalledAt() : null;

            $hasUpdates    = $installed ? $table->hasUpdates() : false;
            $canRollback   = $installed ? $table->canRollback() : false;
            $lastUpdatedAt = $installed ? $table->getLastUpdatedAt() : null;
            $dependencies  = $table->getDependencies();

            $updatesJson = null;
            $rollbackJson = null;

            if ($hasUpdates) {
                $updates = $table->getUpdates();
                $formattedUpdates = [];

                foreach ($updates as $update) {
                    $formattedUpdates[$update['handle']] = json_encode([
                        'label'       => $update['label'], 
                        'description' => $update['description']
                    ]);
                }

                $updatesJson = json_encode($formattedUpdates);
            }

            if ($canRollback) {
                $lastUpdate = $table->getLastUpdate();
                if ($lastUpdate) {
                    $rollbackJson = json_encode([
                        'handle'      => $lastUpdate['handle'],
                        'label'       => $lastUpdate['label'],
                        'description' => $lastUpdate['description']
                    ]);
                }
            }
            
            ob_start();
            echo view('meros::admin.table-card', [
                'provider'      => $providerHandle,
                'name'          => $name,
                'required'      => $required,
                'label'         => Str::title(Str::replace('_', ' ', $name)),
                'description'   => $table->getDescription(),
                'canInstall'    => $canInstall,
                'installed'     => $installed,
                'installedAt'   => $installedAt,
                'hasUpdates'    => $hasUpdates,
                'updates'       => $updatesJson,
                'rollback'      => $rollbackJson,
                'canRollback'   => $canRollback,
                'lastUpdatedAt' => $lastUpdatedAt,
                'enabled'       => $enabled,
                'isPackage'     => $isPackage,
                'dependencies'  => $dependencies,
                'nonce'         => wp_create_nonce('meros_table_action_' . $name . '_' . $providerHandle),
            ]);

            $html = ob_get_clean();
            echo $this->sanitizeHtml($html);
        }

        echo '</div>';
    }

    /**
     * Generates a multi-table action button for the specified action (install, uninstall, update) based on the provided tables.
     *
     * @param Collection $tables
     * @param string     $providerHandle
     * @param string     $action
     * @param bool       $isPackage
     *
     * @return string
     */
    private function getTablesMultiActionButton(Collection $tables, string $providerHandle, string $action, bool $isPackage): string {
        $tablesList = $this->filterTables($tables, function (Table $table) use ($action, $isPackage) {
            if ($action === 'install') {
                return !$table->isInstalled() && $table->canInstall();
            } 
            
            else if ($action === 'update') {
                return $table->isInstalled() && $table->hasUpdates();
            } 
            
            else if ($action === 'uninstall') {
                if ($isPackage) {
                    return $table->isInstalled();
                } else {
                    return $table->isInstalled() && !$table->isRequired();
                }
            }

            return false;
        }, true);

        if ($tablesList === '[]') {
            return '';
        }

        return 
            '<a 
                href="#" 
                class="meros-admin-button meros-small-button meros-tables-action-button button button-primary" 
                data-provider="' . $providerHandle . '"
                data-action="' . $action . '_all"
                data-nonce="' . wp_create_nonce('meros_table_action_' . $action . '_all_' . $providerHandle) . '"
                data-tables=\'' . $tablesList . '\'
            >
                ' . Str::title(Str::replace('_', ' ', $action)) . ' All Tables
            </a>';
    }

    /**
     * Helper method to filter tables based on a callback and return the result as a JSON string of table names.
     *
     * @param Collection $tables
     * @param Closure    $callback
     * @param boolean    $asJsonNames
     *
     * @return Collection|string
     */
    private function filterTables(Collection $tables, Closure $callback, bool $asJsonNames = true): Collection|string {
        $filtered = $tables->filter($callback);

        if ($asJsonNames) {
            return $filtered->map(function (Table $table) {
                return json_encode(['name' => $table->getName()]);
            })->values()->toJson();
        }

        return $filtered;
    }

    /**
     * Validates the table operation request parameters.
     *
     * @param string $table
     * @param string $provider
     * @param string $nonce
     * @param string $operation
     *
     * @return boolean
     */
    private function validateTableOperation(string $table, string $provider, string $nonce, string $operation): bool {
        $isMultiOperation = in_array($operation, ['install_all', 'uninstall_all', 'update_all']);

        if ($isMultiOperation) {
            $validNonce = wp_verify_nonce($nonce, 'meros_table_action_' . $operation . '_' . $provider);
            return $validNonce;
        }

        $validTable = $table !== '';
        $validNonce = wp_verify_nonce($nonce, 'meros_table_action_' . $table . '_' . $provider);
        $validOperation = in_array($operation, ['install', 'uninstall', 'update', 'rollback']);

        return $validTable && $validNonce && $validOperation;
    }

    /**
     * Handles the specified table operation (create, drop, update, rollback).
     *
     * @param string $tableName
     * @param string $operation
     *
     * @return void
     */
    private function handleTableOperation(string $tableName, string $operation): void {
        $isMultiOperation = in_array($operation, ['install_all', 'uninstall_all', 'update_all']);

        if ($isMultiOperation) {
            $this->handleMultiTableOperation($operation);
            return;
        }

        $this->tables()->init();
        $table  = $this->tables()->get($tableName);

        if ($table === null || !($table instanceof Table)) {
            wp_send_json_error(['message' => 'Table not found.']);
            exit;
        }

        if (!method_exists($table, $operation)) {
            wp_send_json_error(['message' => 'Invalid operation.']);
            exit;
        }

        $table->{$operation}();
        $error = $table->getLastError();

        if (!empty($error)) {
            wp_send_json_error(['message' => 'Error during ' . $operation . ': ' . $error]);
            exit;
        }

        $message = ucfirst($operation) . ' operation completed successfully for table: ' . $table->getName();
        wp_send_json_success(['message' => $message]);
        exit;
    }

    /**
     * Handles operations that affect multiple tables, such as install_all, uninstall_all, and update_all.
     *
     * @param string $operation
     *
     * @return void
     */
    private function handleMultiTableOperation(string $operation): void {
        $tables = $this->tables()->init();
        $operation = Str::replaceLast('_all', '', $operation);

        if ($operation === 'uninstall') {
            // Initialise tables in reverse order for uninstall operations
            $tables = $tables->reverse();
        }

        foreach ($tables as $table) {
            if (method_exists($table, $operation)) {
                if ($table->autoInstallsWithDependencies() && $operation === 'install') {
                    // Skip auto-installing tables that are dependencies of other tables
                    continue;
                }

                $table->{$operation}();
                $error = $table->getLastError();

                if (!empty($error)) {
                    wp_send_json_error(['message' => 'Error during ' . $operation . ' for table ' . $table->getName() . ': ' . $error]);
                    exit;
                }
            }
        }

        wp_send_json_success(['message' => ucfirst($operation) . ' operation completed successfully for all tables.']);
        exit;
    }

    /**
     * Attempts to register a table migrations path for the provider. 
     * 
     * This is so a 'manage tables' page can be displayed in the admin area, 
     * even if the provider is disabled (i.e. when it is a package that is not currently active).
     *
     * @return void
     */
    private function registerTables(): void {
        try {
            $this->tables()->register();
        } catch (\InvalidArgumentException $e) {
            // Do nothing if no valid migrations path is provided or registered for the current provider.
        }
    }

}