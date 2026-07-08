<?php

namespace MM\Meros\Services\Controllers;

use MM\Meros\App\Framework;
use MM\Meros\App\Package;
use MM\Meros\App\Models\Migration;
use MM\Meros\Support\SchemaManager;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Facades\Theme as ThemeAccessor;
use MM\Meros\Facades\Packages as PackagesAccessor;

class InstallerController {
    /**
     * Generates installer status and action button markup for a provider.
     *
     * @param FeatureProvider $provider
     * @param string $providerType
     * @return string
     */
    public function renderInstallerHTML(FeatureProvider $provider, string $providerType = 'package'): string {
        $html                 = '';
        $handle               = $provider->getHandle();
        $isPackage            = $provider instanceof Package;
        $resolvedProviderType = $isPackage ? 'package' : $providerType;
        $isThemeProvider      = $resolvedProviderType === 'theme';
        $enabled              = $isPackage && method_exists($provider, 'isEnabled') ? (bool) $provider->isEnabled() : true;
        $installed            = $provider->isInstalled();
        $hasUpdates           = $provider->hasUpdates();
        $installedAt          = null;
        $plans                = $this->getInstallerPlanData($provider);

        $html .= '<div class="meros-provider-tasks">';

        if ($installed) {
            $installedAt = $provider->installedAt() ?? 'Unknown time';
        }

        $dataAttrs  = 'data-provider="' . esc_attr($handle) . '" ';
        $dataAttrs .= 'data-provider-type="' . esc_attr($resolvedProviderType) . '" ';
        $dataAttrs .= 'data-provider-label="' . esc_attr($provider->getName()) . '" ';
        $dataAttrs .= 'data-installer-plan-install="' . esc_attr(wp_json_encode($plans['install'])) . '" ';
        $dataAttrs .= 'data-installer-plan-update="' . esc_attr(wp_json_encode($plans['update'])) . '" ';
        $dataAttrs .= 'data-installer-plan-uninstall="' . esc_attr(wp_json_encode($plans['uninstall'])) . '" ';
        $dataAttrs .= 'data-installer-plan-rollback="' . esc_attr(wp_json_encode($plans['rollback'])) . '" ';
        $dataAttrs .= 'data-nonce="' . esc_attr(wp_create_nonce('meros_provider_install_operation_' . $handle)) . '"';

        if (!$enabled && !$isThemeProvider && $installed) {
            $html .= '<p class="meros-installer-info">Installed: ' . esc_html($installedAt) . '</p>';
            $html .= '<a href="#" class="meros-provider-action-button meros-provider-uninstaller-button button button-primary" ' . $dataAttrs . ' style="margin-top:4px;">Uninstall</a>';
            $html .= '</div>';
            
            return $html;
        }

        if ($installed) {
            $html .= '<p class="meros-installer-info"><span><strong>Installed:</strong> ' . esc_html($installedAt) . '</span>';

            $lastUpdated = $provider->lastUpdated();
            $newInstall  = $lastUpdated === $installedAt;
            $canRollback = $lastUpdated !== $installedAt;

            if (!$newInstall) {
                $html .= '<span> | <strong>Last updated:</strong> ' . esc_html($lastUpdated) . '</span>';
            }

            if ($hasUpdates) {
                $html .= '<span style="color:green"> | <strong>Update available:</strong></span></p>';
            } else {
                $html .= '</p>';
            }

            $html .= '<div class="meros-provider-action-buttons">';

            if ($isThemeProvider || $resolvedProviderType === 'framework') {
                $html .= '<a href="#" class="meros-provider-action-button meros-provider-uninstaller-button button button-primary" ' . $dataAttrs . ' style="margin-top:4px;">Uninstall</a>';
            }
             
            if ($hasUpdates) {
                $html .= '<a href="#" class="meros-provider-action-button meros-provider-update-button button button-primary" ' . $dataAttrs . ' style="margin-top:4px;">Update</a>';
            }

            if ($canRollback) {
                $html .= '<a href="#" class="meros-provider-action-button meros-provider-rollback-button button button-primary" ' . $dataAttrs . ' style="margin-top:4px;">Rollback</a>';
            }

            $html .= '</div>';

        } else {
            $html .= '<p class="meros-installer-info">This ' . esc_html($resolvedProviderType) . ' has features that may need to be installed for it to function properly.</p>';
            $html .= '<a href="#" class="meros-provider-action-button meros-provider-installer-button button button-primary" ' . $dataAttrs . ' style="margin-top:4px;">Install</a>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Builds installer preview data used by admin confirmation dialogs.
     *
     * @param FeatureProvider $provider
     * @return array<string, array<int, array{table:string,change:string}>>
     */
    public function getInstallerPlanData(FeatureProvider $provider): array {
        $plans = [
            'install' => [],
            'update' => [],
            'uninstall' => [],
            'rollback' => [],
        ];

        $tables = method_exists($provider, 'installerTables')
            ? $provider->installerTables()
            : collect([]);

        foreach ($tables as $table) {
            $tableName = $table->getTableName();
            $installed = $table->isInstalled();
            $hasUpdates = $table->hasUpdates();

            if (!$installed) {
                $plans['install'][] = [
                    'table' => $tableName,
                    'change' => 'add',
                ];

                $plans['update'][] = [
                    'table' => $tableName,
                    'change' => 'add',
                ];
            }

            if ($installed && $hasUpdates) {
                $plans['update'][] = [
                    'table' => $tableName,
                    'change' => 'update',
                ];
            }

            if ($installed && $tableName !== 'meros_migrations') {
                $plans['uninstall'][] = [
                    'table' => $tableName,
                    'change' => 'remove',
                ];
            }
        }

        if (!SchemaManager::hasTable('meros_migrations')) {
            return $plans;
        }

        $latestBatchRecords = Migration::where('provider', $provider->getHandle())
            ->orderByDesc('id')
            ->get();

        if ($latestBatchRecords->isNotEmpty()) {
            $latestBatchID = $latestBatchRecords->first()->batch_id;

            foreach ($latestBatchRecords->where('batch_id', $latestBatchID) as $record) {
                $tableName = $record->related_table;
                $change = $record->type === 'create' ? 'remove' : 'rollback';

                if (!isset($plans['rollback'][$tableName])) {
                    $plans['rollback'][$tableName] = [
                        'table' => $tableName,
                        'change' => $change,
                    ];
                }

                if ($change === 'remove') {
                    $plans['rollback'][$tableName]['change'] = 'remove';
                }
            }

            $plans['rollback'] = array_values($plans['rollback']);
        }

        return $plans;
    }

    /**
     * Renders the installer confirmation modal used by admin action buttons.
     *
     * @return string
     */
    public function renderInstallerModalHTML(): string {
        return '
            <div id="meros-installer-modal" class="meros-installer-modal" hidden>
                <div class="meros-installer-modal-backdrop" data-modal-close="1"></div>
                <div class="meros-installer-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="meros-installer-modal-title">
                    <h2 id="meros-installer-modal-title">Confirm Installer Action</h2>
                    <p id="meros-installer-modal-description"></p>
                    <div class="meros-installer-modal-plan">
                        <h3>Affected Tables</h3>
                        <ul id="meros-installer-modal-plan-list"></ul>
                    </div>
                    <div class="meros-installer-modal-actions">
                        <button type="button" id="meros-installer-modal-cancel" class="button button-secondary">Cancel</button>
                        <button type="button" id="meros-installer-modal-confirm" class="button button-primary">Proceed</button>
                    </div>
                </div>
            </div>
        ';
    }

    /**
     * Handles AJAX requests for provider installer operations (install, update, rollback, uninstall).
     *
     * @param Framework $framework
     * @return void
     */
    public function handleProviderInstallerTasks(Framework $framework): void {
        $providerHandle      = sanitize_key($_POST['provider'] ?? '');
        $providerType        = sanitize_key($_POST['providerType'] ?? '');
        $subAction           = $_POST['subAction'] ?? '';
        $nonce               = $_POST['nonce'] ?? '';

        $hasAction           = in_array($subAction, ['install', 'update', 'rollback', 'uninstall']);
        $hasProvider         = is_string($providerHandle) && $providerHandle !== '';
        $isValidProviderType = in_array($providerType, ['package', 'theme', 'framework']);

        $isValid = $hasAction &&
            $hasProvider &&
            $isValidProviderType &&
            wp_verify_nonce($nonce, 'meros_provider_install_operation_' . $providerHandle);

        if (!$isValid) {
            wp_send_json_error([
                'message' => 'Invalid request'
            ]);

            return;
        }

        if ($providerType === 'package') {
            $provider = PackagesAccessor::get($providerHandle);
        } elseif ($providerType === 'framework') {
            $provider = $framework;
        } else {
            $provider = ThemeAccessor::get();
        }

        if ($provider === null) {
            wp_send_json_error([
                'message' => 'Provider not found.'
            ]);
            return;
        }

        try {
            switch ($subAction) {
                case 'install':
                    $provider->install();
                    break;
                case 'update':
                    $provider->update();
                    break;
                case 'rollback':
                    $provider->rollback();
                    break;
                case 'uninstall':
                    $provider->uninstall();
                    break;
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Error performing operation: ' . $e->getMessage(),
            ]);
            return;
        }

        wp_send_json_success([
            'message'  => 'Operation successful'
        ]);
    }
}
