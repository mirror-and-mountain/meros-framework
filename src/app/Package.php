<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;
use MM\Meros\App\Concerns\HasInstaller;
use MM\Meros\App\Facades\Framework;

abstract class Package extends FeatureProvider {
    /**
     * Indicates whether the package is enabled.
     *
     * @var boolean
     */
    private bool $enabled = false;

    use HasInstaller;

    /**
     * Determines the enabled status of the package based on its preferences and installable status.
     *
     * @return void
     */
    private function determineEnabledStatus(): void {
        $enabledByDefault  = $this->getPreference('is_enabled_by_default');
        $switchable        = $this->getPreference('is_switchable');
        $hasInstallables   = $this->hasInstallables;

        if ($enabledByDefault && ! $switchable && ! $hasInstallables) {
            // If the package is enabled by default, not switchable, and has no installables, enable it without showing a switch in WP Admin.
            $this->enabled = true;
        }

        else if ($switchable && ! $hasInstallables) {
            // If the package is switchable but has no installables, enable or disable it based on the saved option value.
            $this->enabled = (bool) get_option($this->handle . '_enable', $enabledByDefault);
        }

        else if (! $switchable && $hasInstallables) {
            if (! Framework::isServiceInstalled('core')) {
                $this->enabled = false; // If Meros isn't installed, we can't run installables, so the package can't be enabled.
                return;
            }

            // If the package is not switchable but has installables, enable it if it's installed, but don't show a switch in WP Admin.
            $this->enabled = $this->isInstalled();
        }

        else if ($switchable && $hasInstallables) {
            if (! Framework::isServiceInstalled('core')) {
                $this->enabled = false; // If Meros isn't installed, we can't run installables, so the package can't be enabled.
                return;
            }

            // If the package is switchable and has installables, enable it if it's installed and the saved option value is true.
            $this->enabled = $this->isInstalled() && (bool) get_option($this->handle . '_enable', $enabledByDefault);
        }
    }

    /**
     * Makes a switch for the package on the Features settings page in WP Admin.
     *
     * @return void
     */
    private function makePackageSwitch(): void {
        $settingsPage = 'meros_features';
        $tab          = 'packages';
        $optionGroup  = $settingsPage . '_' . $tab;

        $settingsSectionID = Str::snake($this->author) . '_packages';
    
        // Check if a settings section for the package switch already exists, and if not, create it.
        $section = $this->registry->get('settingsSections')->firstWhere('handle', $settingsSectionID);

        if ($section === null) {
            $section = $this->makeSettingsSection([
                'id'    => $settingsSectionID,
                'page'  => $optionGroup,
                'callback' => function() use ($settingsSectionID) {
                    $content  = "<h3 id=\"{$settingsSectionID}\" style=\"margin-bottom: -8px;\">Provided by {$this->author}</h3><p style=\"margin-bottom: -8px;\">";
                    $content .= $this->authorUri !== '' ? "<a href=\"{$this->authorUri}\" target=\"_blank\">Website</a>" : '';
                    $content .= $this->authorSupportUri !== '' ? " | <a href=\"{$this->authorSupportUri}\" target=\"_blank\">Support</a>" : '';
                    $content .= '</p>';
                    
                    $content = apply_filters("{$settingsSectionID}_content", $content);

                    echo $content;
                }
            ]);
        }

        // The setting config
        $setting = [
            'option_name'  => $this->handle . '_enable',
            'option_group' => $optionGroup,
            'type'         => 'boolean',
            'label'        => "Enable {$this->name}",
            'description'  => $this->description,
            'default'      => $this->enabled
        ];

        // The field config for the setting
        $disabled = $this->hasInstallables && ! $this->isInstalled() || $this->hasInstallables && ! Framework::isServiceInstalled('core');
        
        $field = [
            'page'            => $optionGroup,
            'section'         => $settingsSectionID,
            'type'            => 'toggle',
            'title'           => $this->getPackageSwitchTitleHTML(),
            'disabled'        => $disabled,
            'ajax_action'     => 'meros_toggle_package',
            'data_attributes' => ['package' => $this->handle],
            'callback'        => 'default',
            'nonce'           => 'meros_toggle_package_' . $this->handle
        ];

        $this->makeSetting($setting, $field);
    }

    /**
     * Generates the HTML for package switch titles.
     *
     * @return string
     */
    private function getPackageSwitchTitleHTML(): string {
        // Generate HTML for the label
        $label = '<label id="' . esc_attr($this->handle) . '_enable_label" for="' . esc_attr($this->handle) . '_enable" class="meros-settings-label">Enable ' . esc_html($this->name) . '</label>';
        
        // Generate HTML for the description
        $description = $this->description !== ''
            ? '<p class="description">' . esc_html($this->description) . '</p>'
            : '';

        // Generate HTML for the tasks area
        if ($this->hasInstallables) {
            $installerHTML = $this->makeInstaller('meros-package-tasks');
        }

        return $label . $description . ($this->hasInstallables ? $installerHTML : '');
    }
}
