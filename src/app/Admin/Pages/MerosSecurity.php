<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Contracts\Features\Security\Capability;
use MM\Meros\Contracts\Features\Security\Role;
use MM\Meros\Facades\Admin\SettingsContainers;
use MM\Meros\Facades\Security\Capabilities as CapabilitiesFacade;
use MM\Meros\Facades\Security\Roles as RolesFacade;

final class MerosSecurity extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-security');
        $this->title('Security');
        $this->menuTitle('Security');
        $this->area('options');
        $this->position(0);
        $this->hideSettings(true);

        add_filter('editable_roles', [Role::class, 'filterEditableRoles']);
        add_action('admin_post_meros_save_role_settings', [$this, 'saveRoleSettings']);

        $this->callback(function () {
            $tabs = [
                'mfa'  => 'Multi-Factor Authentication',
                'permissions' => 'Manage Permissions',
            ];

            $activeTab = isset($_GET['tab']) && is_string($_GET['tab'])
                ? sanitize_key(wp_unslash($_GET['tab']))
                : 'mfa';

            if (!array_key_exists($activeTab, $tabs)) {
                $activeTab = 'mfa';
            }

            echo '<nav class="nav-tab-wrapper">';
            foreach ($tabs as $tab => $label) {
                $activeClass = $activeTab === $tab ? ' nav-tab-active' : '';
                echo '<a href="' . esc_url(add_query_arg(['page' => 'meros-security', 'tab' => $tab], admin_url('admin.php'))) . '" class="nav-tab' . esc_attr($activeClass) . '">' . esc_html($label) . '</a>';
            }
            echo '</nav>';

            if ($activeTab === 'mfa') {
                echo '<form method="post" action="options.php">';
                settings_fields($this->optionGroup);
                do_settings_sections($this->slug);
                submit_button();
                echo '</form>';
            } elseif ($activeTab === 'permissions') {
                echo $this->getPermissionsTabContent();
            }
        });
    }

    /**
     * Returns the HTML content for the Permissions tab in the Meros Security page.
     *
     * @return string
     */
    private function getPermissionsTabContent(): string {
        $settings = Role::getSettings();
        $hiddenRoles = $settings['hidden_default_roles'] ?? [];
        if (!is_array($hiddenRoles)) {
            $hiddenRoles = [];
        }
        $wordpressRoles = wp_roles()->roles;
        $builtInRoles = [];
        foreach (Role::BUILT_IN_ROLES as $handle) {
            if (isset($wordpressRoles[$handle])) {
                $builtInRoles[$handle] = $wordpressRoles[$handle]['name'];
            }
        }

        $capabilities = [];
        foreach (Capability::CORE_CAPABILITIES as $name) {
            $capabilities[$name] = [
                'label' => ucwords(str_replace('_', ' ', $name)),
                'description' => '',
                'core' => true,
            ];
        }
        foreach (CapabilitiesFacade::all() as $capability) {
            if ($capability instanceof Capability && !isset($capabilities[$capability->getIdentifier()])) {
                $capabilities[$capability->getIdentifier()] = [
                    'label' => $capability->getLabel(),
                    'description' => $capability->getDescription(),
                    'core' => false,
                ];
            }
        }
        uasort($capabilities, fn (array $a, array $b) =>
            ($b['core'] <=> $a['core']) ?: strcasecmp($a['label'], $b['label']));
        $customRoles = RolesFacade::all()
            ->filter(fn ($role) => $role instanceof Role)
            ->sortBy(fn (Role $role) => $role->getLabel())
            ->values();
        $customWordPressRoles = [];
        foreach ($customRoles as $role) {
            $customWordPressRoles[$role->getIdentifier()] = get_role($role->getIdentifier());
        }
        $configurableCapabilities = [];
        foreach (array_merge(array_keys($builtInRoles), $customRoles->map(fn (Role $role) => $role->getIdentifier())->all()) as $handle) {
            $configurableCapabilities[$handle] = Role::getConfigurableCapabilities($handle);
        }

        ob_start();
        ?>
        <h2>Roles and Capabilities</h2>
        <p class="description">Assign WordPress capabilities to built-in roles and opted-in custom roles. Administrator is read-only and always receives every registered theme/package-defined capability. Theme/package-defined assignments retain their opt-in restrictions.</p>
        <p class="description">Changes affect every user assigned to a role. Powerful capabilities such as Manage Options or Promote Users can grant administrative access. WordPress may further restrict some capabilities on multisite or through site configuration.</p>
        <?php if (($_GET['roles-saved'] ?? null) === '1'): ?>
            <div class="notice notice-success inline"><p>Role settings saved.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="meros_save_role_settings">
            <?php wp_nonce_field('meros_save_role_settings'); ?>

            <h3>Capability assignments</h3>
            <div class="meros-role-matrix-wrap" style="overflow-x:auto;">
                <table class="widefat striped meros-role-matrix" aria-label="Role capability assignments">
                    <thead>
                        <tr>
                            <th scope="col" rowspan="2">Capability</th>
                            <th scope="colgroup" colspan="<?php echo count($builtInRoles); ?>">Built-in WordPress roles</th>
                            <?php if ($customRoles->isNotEmpty()): ?>
                                <th scope="colgroup" colspan="<?php echo $customRoles->count(); ?>">Theme/package-defined roles</th>
                            <?php endif; ?>
                        </tr>
                        <tr>
                            <?php foreach ($builtInRoles as $handle => $label): ?>
                                <th scope="col">
                                    <strong><?php echo esc_html($label); ?></strong>
                                    <?php if ($handle === 'administrator'): ?>
                                        <br><span class="description">Always available</span>
                                    <?php else: ?>
                                        <br>
                                        <label class="description">
                                            <input
                                                type="checkbox"
                                                name="meros_hidden_default_roles[]"
                                                value="<?php echo esc_attr($handle); ?>"
                                                <?php checked(in_array($handle, $hiddenRoles, true)); ?>
                                            >
                                            Hide from assignments
                                        </label>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            <?php foreach ($customRoles as $role): ?>
                                <th scope="col">
                                    <strong><?php echo esc_html($role->getLabel()); ?></strong>
                                    <?php if ($role->getDescription() !== ''): ?>
                                        <br><span class="description"><?php echo esc_html($role->getDescription()); ?></span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $previousGroup = null; ?>
                        <?php foreach ($capabilities as $capabilityName => $capability): ?>
                            <?php if ($previousGroup !== $capability['core']): ?>
                                <tr>
                                    <th colspan="<?php echo 1 + count($builtInRoles) + $customRoles->count(); ?>" scope="rowgroup">
                                        <?php echo $capability['core'] ? 'WordPress capabilities' : 'Theme/package-defined capabilities'; ?>
                                    </th>
                                </tr>
                            <?php endif; ?>
                            <?php $previousGroup = $capability['core']; ?>
                            <tr>
                                <th scope="row">
                                    <strong><?php echo esc_html($capability['label']); ?></strong>
                                    <br><code><?php echo esc_html($capabilityName); ?></code>
                                    <?php if ($capability['description'] !== ''): ?>
                                        <p class="description"><?php echo esc_html($capability['description']); ?></p>
                                    <?php endif; ?>
                                </th>
                                <?php foreach ($builtInRoles as $handle => $label): ?>
                                    <?php
                                    $enabled = !empty($wordpressRoles[$handle]['capabilities'][$capabilityName]);
                                    $editable = in_array($capabilityName, $configurableCapabilities[$handle], true);
                                    $inputId = 'capability-' . $capabilityName . '-' . $handle;
                                    ?>
                                    <td>
                                        <label class="screen-reader-text" for="<?php echo esc_attr($inputId); ?>">
                                            <?php echo esc_html($capability['label'] . ' for ' . $label); ?>
                                        </label>
                                        <input
                                            id="<?php echo esc_attr($inputId); ?>"
                                            type="checkbox"
                                            name="meros_role_capabilities[<?php echo esc_attr($handle); ?>][<?php echo esc_attr($capabilityName); ?>]"
                                            value="1"
                                            <?php checked($enabled); ?>
                                            <?php disabled(!$editable); ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                                <?php foreach ($customRoles as $role): ?>
                                    <?php
                                    $roleHandle = $role->getIdentifier();
                                    $roleCapabilities = $role->getCapabilities();
                                    $wordpressRole = $customWordPressRoles[$roleHandle];
                                    $enabled = $wordpressRole
                                        ? $wordpressRole->has_cap($capabilityName)
                                        : ($roleCapabilities[$capabilityName] ?? false);
                                    $editable = in_array($capabilityName, $configurableCapabilities[$roleHandle], true);
                                    $inputId = 'capability-' . $capabilityName . '-' . $roleHandle;
                                    ?>
                                    <td>
                                        <label class="screen-reader-text" for="<?php echo esc_attr($inputId); ?>">
                                            <?php echo esc_html($capability['label'] . ' for ' . $role->getLabel()); ?>
                                        </label>
                                        <input
                                            id="<?php echo esc_attr($inputId); ?>"
                                            type="checkbox"
                                            name="meros_role_capabilities[<?php echo esc_attr($roleHandle); ?>][<?php echo esc_attr($capabilityName); ?>]"
                                            value="1"
                                            <?php checked($enabled); ?>
                                            <?php disabled(!$editable); ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <input type="hidden" name="meros_role_settings_complete" value="1">
            <?php submit_button('Save Role Settings'); ?>
        </form>
        <?php

        $content = ob_get_clean();
        if (!is_string($content)) {
            throw new \RuntimeException('Unable to render the role settings form.');
        }

        return $content;
    }

    /**
     * Saves the role settings submitted from the Permissions tab in the Meros Security page.
     *
     * @return void
     */
    public function saveRoleSettings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage roles.');
        }

        check_admin_referer('meros_save_role_settings');

        if (($_POST['meros_role_settings_complete'] ?? null) !== '1') {
            wp_die('The role settings submission was incomplete. No changes were saved. Check the PHP max_input_vars setting.');
        }

        $submittedHiddenRoles = $_POST['meros_hidden_default_roles'] ?? [];
        if (!is_array($submittedHiddenRoles)) {
            wp_die('Invalid hidden role settings. No changes were saved.');
        }
        $submittedHiddenRoles = wp_unslash($submittedHiddenRoles);

        $hideableRoles = array_diff(Role::BUILT_IN_ROLES, ['administrator']);
        $hiddenRoles = array_values(array_intersect(
            $hideableRoles,
            array_filter($submittedHiddenRoles, 'is_string')
        ));

        $submittedCapabilities = $_POST['meros_role_capabilities'] ?? [];
        if (!is_array($submittedCapabilities)) {
            wp_die('Invalid capability settings. No changes were saved.');
        }
        $submittedCapabilities = wp_unslash($submittedCapabilities);

        $roleCapabilities = [];
        $roleHandles = array_merge(
            array_diff(Role::BUILT_IN_ROLES, ['administrator']),
            RolesFacade::all()->filter(fn ($role) => $role instanceof Role)
                ->map(fn (Role $role) => $role->getIdentifier())->all()
        );
        foreach ($roleHandles as $roleHandle) {
            $submittedRoleCapabilities = $submittedCapabilities[$roleHandle] ?? [];
            if (!is_array($submittedRoleCapabilities)) {
                wp_die('Invalid role capability settings. No changes were saved.');
            }

            $roleCapabilities[$roleHandle] = [];
            foreach (Role::getConfigurableCapabilities($roleHandle) as $capabilityName) {
                if (isset($submittedRoleCapabilities[$capabilityName]) && $submittedRoleCapabilities[$capabilityName] !== '1') {
                    wp_die('Invalid capability value. No changes were saved.');
                }
                $roleCapabilities[$roleHandle][$capabilityName] =
                    ($submittedRoleCapabilities[$capabilityName] ?? null) === '1';
            }
        }

        $settings = [
            'hidden_default_roles' => $hiddenRoles,
            'role_capabilities' => $roleCapabilities,
        ];
        $container = Role::resolveSettingsContainer(SettingsContainers::instance());
        foreach ($settings as $key => $value) {
            $container->setItemValue($key, $value);
        }
        $storedSettings = $container->getValue(true);
        foreach ($settings as $key => $value) {
            if (($storedSettings[$key] ?? null) !== $value) {
                wp_die('Role settings could not be saved.');
            }
        }
        Role::applyBuiltInCapabilitySettings();
        foreach (RolesFacade::all() as $role) {
            if ($role instanceof Role) {
                $role->register();
            }
        }

        wp_safe_redirect(add_query_arg(
            ['page' => 'meros-security', 'tab' => 'permissions', 'roles-saved' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }
}