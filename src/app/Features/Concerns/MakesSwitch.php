<?php 

namespace MM\Meros\App\Features\Concerns;

use Illuminate\Support\Str;

use MM\Meros\App\Features\Asset;
use MM\Meros\App\Features\Block;

use MM\Meros\App\Features\Settings\Setting;
use MM\Meros\App\Features\Settings\SettingsSection;
use MM\Meros\App\Facades\Registry;

trait MakesSwitch {
    /**
     * Indicates whether the item can be toggled in WP Admin.
     *
     * @var boolean
     */
    public bool $isSwitchable;

    /**
     * The setting instance for the switch if the item is switchable.
     *
     * @var Setting
     */
    public Setting $switchSetting;
    
    /**
     * Makes a switch for the switchable item so it can be toggled on and off in WP Admin.
     *
     * @return void
     */
    private function makeSwitch(): void {
        // Determine the section suffix based on the feature type.
        $sectionSuffix = $this instanceof Block ? 'blocks' : ($this instanceof Asset ? 'assets' : null);

        if ($sectionSuffix === null) {
            return;
        }
        
        // Make author settings section slug for the settings section.
        $settingsSectionID = Str::snake($this->source->author) . '_' . $sectionSuffix;

        // Check if a settings section for the asset switch already exists, and if not, create it.
        $section = Registry::get('settingsSections')->firstWhere('handle', $settingsSectionID);

        if ($section === null) {
            $section = $this->makeSettingsSection([
                'id'    => $settingsSectionID,
                'page'  => 'meros_theme_settings_' . $sectionSuffix,
                'callback' => function() use ($settingsSectionID) {
                    $content  = "<h3 id=\"{$settingsSectionID}\" style=\"margin-bottom: -8px;\">Provided by {$this->source->author}</h3><p style=\"margin-bottom: -8px;\">";
                    $content .= $this->source->authorUri !== '' ? "<a href=\"{$this->source->authorUri}\" target=\"_blank\">Website</a>" : '';
                    $content .= $this->source->authorSupportUri !== '' ? " | <a href=\"{$this->source->authorSupportUri}\" target=\"_blank\">Support</a>" : '';
                    $content .= '</p>';
                    
                    $content = apply_filters("{$settingsSectionID}_content", $content);

                    echo $content;
                }
            ]);
        }

        // The setting config
        $setting = [
            'option_name'  => $this->handle . '_enable',
            'option_group' => 'meros_theme_settings_' . $sectionSuffix,
            'type'         => 'boolean',
            'label'        => "Enable {$this->label}",
            'description'  => $this->description,
            'default'      => $this->enabled
        ];

        // The field config for the setting
        $field = [
            'page'     => 'meros_theme_settings_' . $sectionSuffix,
            'section'  => $settingsSectionID,
            'type'     => 'checkbox',
            'title'    => $this->getSwitchTitleHTML(),
            'callback' => 'default'
        ];

        // Make the setting
        $this->switchSetting = $this->makeSetting($setting, $field);

        // Set enabled state based on the setting value.
        $this->setEnabled();
    }

    /**
     * Creates a SettingsSection instance for the author and registers it.
     * 
     * @param  array $config Config for the settings section.
     * 
     * @return SettingsSection The created SettingsSection instance.
     */
    private function makeSettingsSection(array $config): SettingsSection {
        return app(SettingsSection::class, ['source' => $this->source])->make($config);
    }

     /**
     * Creates a Setting instance for the item and registers it.
     * 
     * @param  array $config Config for the setting.
     * 
     * @return Setting The created Setting instance.
     */
    private function makeSetting(array $config, array $field): Setting {
        return app(Setting::class, ['source' => $this->source])->make($config)->withField($field);
    }

    /**
     * Generates the HTML for the switch title, including a link to the feature.
     *
     * @return string The generated HTML for the switch title.
     */
    private function getSwitchTitleHTML(): string {
        $html = '<label id="' . esc_attr($this->handle) . '_enable_label" for="' . esc_attr($this->handle . '_enable') . '" class="meros-settings-label">' . esc_html($this->label);

        $isByPackage = $this->source instanceof \MM\Meros\App\Package;

        if ($isByPackage) {
            $featureURL  = admin_url('options-general.php?page=meros_features&tab=packages#' . $this->source->handle . '_enable_label');
            $html .= " | <a style=\"font-weight:400;\" href=\"{$featureURL}\">View Feature</a></label>";
        } else {
            $html .= '</label>';
        }

        if ($this->description !== '') {
            $html .= '<p class="description">' . esc_html($this->description) . '</p>';
        }

        return $html;
    }

    /**
     * Determines whether the switchable item is enabled based on the associated setting value.
     *
     * @return boolean
     */
    private function setEnabled(): void {
        $enabled = get_option($this->handle . '_enable', $this->enabled);
        $this->enabled = (bool) $enabled; // Update enabled state based on user preference.
    }
}