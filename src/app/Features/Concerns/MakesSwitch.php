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
     * Indicates whether the item is enabled or disabled. This will be
     * determined by the value of the switch setting if the item is switchable.
     *
     * @var boolean
     */
    public bool $enabled = true;

    /**
     * The setting instance for the switch if the item is switchable.
     *
     * @var Setting
     */
    public Setting $switchSetting;

    /**
     * Chainable method to make the item switchable in WP Admin.
     *
     * @param  boolean $isSwitchable
     *
     * @return self
     */
    public function switchable(bool $isSwitchable = true): self {
        if (isset($this->label) && isset($this->description)) {
            $this->isSwitchable = $isSwitchable;
        } else {
            $this->error = "The 'label' and 'description' fields must be set before making the asset switchable.";
        }

        $this->setReady();
        return $this;
    }

    public function withSwitch(): self {
        if (!$this->isSwitchable || !$this->ready) {
            return $this;
        }
    }
    
    /**
     * Makes a switch for the switchable item so it can be toggled on and off in WP Admin.
     *
     * @return void
     */
    private function makeSwitch(): void {
        $isBlock = $this instanceof Block;
        $isAsset = $this instanceof Asset;

        if (! $isBlock && ! $isAsset) {
            return; // Switches are currently only implemented for blocks and assets.
        }

        // Determine the section suffix based on the feature type.
        $sectionSuffix = $isBlock ? 'blocks' : ($isAsset ? 'assets' : null);

        if ($sectionSuffix === null) {
            return;
        }

        // Determine the option name for the switch setting based on the feature type.
        $optionName = '';
        if ($isBlock) {
            $optionName = $this->handle . '_block_enable';
        } 
        
        else if ($isAsset) {
            $optionName = $this->source->handle . '_' . ($this->group ?? '') . '_asset_enable';
        }

        if (Registry::get('settings')->where('handle', $optionName)->count() > 0) {
            return; // Stop if a setting with the same name exists
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
            'option_name'  => $optionName,
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
            'title'    => $this->getSwitchTitleHTML($isBlock ? 'block' : 'asset'),
            'callback' => 'default'
        ];

        // Make the setting
        $this->switchSetting = $this->makeSetting($setting, $field);

        // Recheck enabled state
        $this->enabled = (bool) get_option($optionName, $this->enabled);
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
     * @param string $type The type of the switchable item (e.g., 'block', 'asset').
     *
     * @return string The generated HTML for the switch title.
     */
    private function getSwitchTitleHTML(string $type): string {
        $handle = $this->handle;

        if ($type === 'asset') {
            $handle = $this->source->handle . '_' . $this->group;
        }

        $html = '<label id="' . esc_attr($handle) . '_enable_label" for="' . esc_attr($handle . '_enable') . '" class="meros-settings-label">' . esc_html($this->label);

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
}