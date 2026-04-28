<?php

namespace MM\Meros\App;

use MM\Meros\Services\Contracts\FeatureProvider;

abstract class Theme extends FeatureProvider {

    /**
     * Adds a Wordpress theme support.
     * 
     * @param  string $support
     * @param  mixed  ...$args
     * 
     * @return void
     */
    protected function addThemeSupport(string $support, mixed ...$args): void {
        add_theme_support($support, $args);
    }

    /**
     * Creates an installer for the theme.
     *
     * @return void
     */
    private function makeThemeInstaller(): void {
        $settingsPage = 'meros_features';
        $tab          = 'theme';
        $optionGroup  = $settingsPage . '_' . $tab;

        $settingsSectionID = 'meros_theme_installer';

        // Check if the settings section already, and if not, create it.
        $section = $this->registry->get('settingsSections')->firstWhere('handle', $settingsSectionID);

        if ($section === null) {
            $this->makeSettingsSection([
                'id'       => $settingsSectionID,
                'page'     => $optionGroup,
                'callback' => function() use ($settingsSectionID) {
                    echo "<h3 id=\"{$settingsSectionID}\" style=\"margin-bottom: -8px;\">Theme Installer</h3>";
                }
            ]);
        }

        // The setting config
        $setting = [
            'option_name'  => 'meros_theme_installer',
            'option_group' => $optionGroup,
            'type'         => 'boolean',
            'label'        => 'Theme Installer',
            'description'  => 'Install required and recommended plugins for this theme.',
        ];

        // The field config for the setting
        $field = [
            'page'     => $optionGroup,
            'section'  => $settingsSectionID,
            'title'    => $this->getThemeInstallerTitleHTML(),
            'type'     => 'custom_html',
            'callback' => function() {
                $btn = $this->makeInstaller('', 'meros-installer-info', true);
                echo isset($btn['button']) ? $btn['button'] : '';
            }
        ];

        $this->makeSetting($setting, $field);
    }

    /**
     * Generates the HTML for the theme installer title and description.
     *
     * @return string
     */
    private function getThemeInstallerTitleHTML(): string {
        return '<p class="description meros-settings-label">Install required and recommended plugins for this theme.</p>';
    }

    /**
     * Initialises the theme stylesheet
     * 
     * @return void
     */
    public function initialiseStyleSheet(): void {
        add_action('wp_enqueue_scripts', function () {
            $handle = $this->handle . '_style'; // e.g. meros_style.
            wp_enqueue_style(
                $handle,
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css')
            );
        });
    }

    /**
     * Gets the instance of the theme.
     *
     * @return Theme
     */
    final public function instance(): Theme {
        return $this;
    }
}
