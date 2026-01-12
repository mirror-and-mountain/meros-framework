<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use MM\Meros\Helpers\Fields;

trait SettingsManager
{
    /**
     * The given settings for the feature. These are translated
     * into registered settings with wp's register_setting function.
     */
    protected array $fqSettings = [];

    protected array $settings = [];

    /**
     * The name of the setting used to enable/disable
     * the feature if it is user switchable.
     */
    private string $featureEnabledSettingName = '';

    /**
     * Whether to use the full feature name in settings
     * option names. If false, only the feature slug
     * will be used.
     */
    protected bool $useFullNameInSettings = true;

    /**
     * The capability required to edit this feature's settings
     * in the WP dashboard.
     */
    protected string $settingsCapability = 'manage_options';

    /**
     * The sections for the feature's settings.
     */
    protected array $settingsSections = [];

    /**
     * Valid option types for settings.
     */
    protected array $validOptionTypes = [
        'text',
        'url',
        'email',
        'password',
        'date',
        'textarea',
        'number',
        'integer',
        'boolean',
        'select',
        'multi_select',
        'color',
    ];

    /**
     * Default setting configuration.
     */
    protected array $defaultSettingConfig = [
        'type' => 'text',
        'description' => '',
        'hasField' => true,
        'fieldType' => '',
        'default' => null,
        'required' => false,
        'options' => [],
        'sanitize_callback' => null,
    ];

    /**
     * Mapping of setting types to field types.
     */
    protected array $settingFieldTypes = [
        'text' => 'text',
        'url' => 'url',
        'email' => 'email',
        'password' => 'password',
        'date' => 'date',
        'textarea' => 'textarea',
        'number' => 'number',
        'integer' => 'number',
        'boolean' => ['checkbox', 'toggle', 'button'],
        'select' => 'select',
        'multi_select' => 'select',
        'color' => 'color',
    ];

    /**
     * Creates a setting to enable/disable the feature
     * if it is user switchable.
     */
    private function createFeatureSwitchSetting(string $description = '', bool $isExperimental = false): void
    {
        $label = 'Enable '.Str::title(Str::replace('_', ' ', $this->name));

        $result = $this->addSetting(
            'enabled',
            $label,
            'theme_features',
            $isExperimental ? 'experimental-features' : 'features',
            '',
            '',
            [
                'type' => 'boolean',
                'description' => $description !== ''
                    ? $description
                    : "Enable or disable the {$this->name}.",
                'default' => '1',
                'hasField' => true,
                'fieldType' => 'toggle',
            ],
            false
        );

        if (is_string($result)) {
            $this->featureEnabledSettingName = $result;
        }
    }

    /**
     * Creates a setting to enable/disable a block
     * provided by the feature.
     */
    private function createBlockSwitchSetting(
        string $blockName,
        string $description = '',
        bool $isExperimental = false
    ): string|bool {
        $blockName = Str::slug($blockName, '_');
        $label = 'Enable '.Str::title(Str::replace(['_', '-'], ' ', $blockName));

        if ($isExperimental) {
            $label .= ' (Experimental)';
        }

        $result = $this->addSetting(
            "enable_{$blockName}_block",
            $label,
            'theme_settings',
            'blocks',
            '',
            '',
            [
                'type' => 'boolean',
                'description' => $description !== ''
                    ? $description
                    : "Enable or disable the {$blockName} block.",
                'default' => true,
                'hasField' => true,
            ]
        );

        return $result;
    }

    /**
     * Adds a setting to the specified settings page.
     * This method will also create a settings section.
     */
    protected function addSetting(
        string $name,
        string $label,
        string $page = 'theme_settings',
        string $tab = '',
        string $sectionId = '',
        string $sectionTitle = '',
        array $config = [
            'type' => 'text',
            'description' => '',
            'hasField' => true,
            'fieldType' => '',
            'default' => null,
            'required' => false,
            'options' => [],
            'sanitize_callback' => null,
        ],
        bool $linkToFeature = true
    ): string|bool {
        // Merge and filter config to only allow keys present in defaultSettingConfig
        $config = array_merge($this->defaultSettingConfig, $config);
        $config = array_intersect_key($config, $this->defaultSettingConfig);

        // Validate setting type
        if (! in_array($config['type'], $this->validOptionTypes)) {
            return false;
        }

        // Validate field type
        if ($config['fieldType'] === '') {
            $fieldType = $this->settingFieldTypes[$config['type']];
            if (is_array($fieldType)) {
                $config['fieldType'] = $fieldType[0];
            } else {
                $config['fieldType'] = $fieldType;
            }
        } else {
            $validFieldTypes = is_array($this->settingFieldTypes[$config['type']])
                ? $this->settingFieldTypes[$config['type']]
                : [$this->settingFieldTypes[$config['type']]];

            if (! in_array($config['fieldType'], $validFieldTypes)) {
                return false;
            }
        }

        // Validate page exists
        $optionPageSlug = '';
        $optionsPages = $this->theme->getOptionsPages();
        if (! array_key_exists($page, $optionsPages)) {
            return false;
        } else {
            $optionPageSlug = $optionsPages[$page]['menu_slug'];
        }

        // Validate tab exists or is added
        $tab = $tab === '' ? 'miscellaneous' : Str::slug($tab, '_');

        if (! in_array($tab, $optionsPages[$page]['tabs'])) {
            $this->theme->addOptionsPageTab($page, $tab);
        }

        // Generate section ID if not provided
        if ($sectionId === '') {
            $sectionId = $this->name.'_'.$page.'_'.$tab.'_section';
        }

        // Ensure hasField is set
        if (! isset($config['hasField'])) {
            $config['hasField'] = true;
        }

        // Sanitize config
        $sanitizedConfig = [
            'type' => is_string($config['type']) ? $config['type'] : 'text',
            'description' => is_string($config['description']) ? $config['description'] : '',
            'hasField' => is_callable($config['hasField']) ? $config['hasField'] : (bool) $config['hasField'],
            'fieldType' => is_string($config['fieldType']) ? $config['fieldType'] : '',
            'default' => $config['default'] ?? null,
            'required' => isset($config['required']) ? (bool) $config['required'] : false,
            'options' => is_array($config['options']) ? $config['options'] : [],
            'sanitize_callback' => is_callable($config['sanitize_callback']) ? $config['sanitize_callback'] : null,
        ];

        // Add the setting and corresponding section
        $optionGroup = $optionPageSlug.'_'.$tab;
        // Add settings section
        $this->addSettingsSection($sectionId, $sectionTitle, $optionPageSlug, $tab, $linkToFeature);

        // Register setting and return option name
        return $this->registerSetting(
            $name,
            $label,
            $optionGroup,
            $sectionId,
            $sanitizedConfig
        );
    }

    /**
     * Registers a setting with Wordpress and retrieves its
     * current value.
     */
    private function registerSetting(
        string $name,
        string $label,
        string $optionGroup,
        string $sectionId,
        array $config
    ): string {
        $featureName = $this->name;
        $optionName = $this->useFullNameInSettings
            ? $this->fullName.'_'.$name
            : $featureName.'_'.$name;

        $current = get_option($optionName, $config['default'] ?? null);
        $this->fqSettings[$optionGroup][$optionName] = $current;
        $this->settings[$optionGroup][$name] = $current;

        add_action('admin_init', function () use (
            $optionName,
            $label,
            $optionGroup,
            $sectionId,
            $config
        ) {
            register_setting(
                $optionGroup, $optionName, [
                    'type' => $config['type'],
                    'default' => $config['default'],
                    'description' => $config['description'],
                    'sanitize_callback' => function (mixed $value) use ($config): mixed {
                        if ($config['sanitize_callback'] !== null) {
                            return call_user_func($config['sanitize_callback'], $value);
                        } else {
                            return $this->sanitizeSetting($value, $config);
                        }
                    },
                ]
            );

            if ($config['hasField']) {
                // Add a settings field if specified
                $type = $config['type'] === 'integer' ? 'number' : $config['type'];
                $label = '<label id="'.esc_attr($optionName).'" for="'.esc_attr($optionName).'" class="meros-settings-label">'.esc_html($label).'</label>';
                $description = $config['description'] !== ''
                    ? '<p class="description">'.esc_html($config['description']).'</p>'
                    : '';

                add_settings_field(
                    $optionName,
                    $label.$description,
                    function () use ($optionName, $config, $type) {
                        if (is_callable($config['hasField'])) {
                            call_user_func($config['hasField']);
                        } else {
                            echo Fields::make(
                                $optionName,
                                $type,
                                $config['default'],
                                $config['fieldType'],
                                $optionName,
                                $config['required'],
                                $config['options']
                            );
                        }
                    },
                    $optionGroup,
                    $sectionId
                );
            }
        });

        return $optionName;
    }

    /**
     * Adds a settings section to the specified settings page.
     */
    protected function addSettingsSection(
        string $id,
        string $title,
        string $page,
        string $tab,
        bool $linkToFeature = true
    ): void {
        if (isset($this->settingsSections[$id])) {
            return;
        }

        $this->settingsSections[$id] = [
            'title' => $title,
            'page' => $page,
            'tab' => $tab,
        ];

        add_action('admin_init', function () use ($id, $title, $page, $tab, $linkToFeature) {
            add_settings_section(
                $id,
                $title,
                function () use ($id, $linkToFeature) {
                    $content = '';
                    if ($this->authorName !== 'Unknown') {
                        $content = "<h3 style=\"margin-bottom: -8px;\">Provided by {$this->authorName}</h3><p style=\"margin-bottom: -8px;\">";
                        $content .= $this->authorUrl !== '' ? "<a href=\"{$this->authorUrl}\" target=\"_blank\">Website</a>" : '';
                        $content .= $this->authorSupportUrl !== '' ? " | <a href=\"{$this->authorSupportUrl}\" target=\"_blank\">Support</a>" : '';

                        if ($linkToFeature && $this->featureEnabledSettingName !== '') {
                            $tab = $this->experimental ? 'experimental-features' : 'features';
                            $featureUrl = admin_url('options-general.php?page=theme_features&tab='.$tab.'#'.$this->featureEnabledSettingName);
                            $content .= " | <a href=\"{$featureUrl}\">View Feature</a>";
                        }

                        $content .= '</p>';
                        $content = apply_filters($this->name.'_settings_section_'.$id.'_content', $content);
                    }
                    echo $content;
                },
                $page.'_'.$tab,
                []
            );
        }, 5);
    }

    /**
     * Callback to sanitize a setting when modified in the WP dashboard.
     */
    private function sanitizeSetting(mixed $value, array $config): mixed
    {
        $requiredType = $config['type'];
        $type = gettype($value);

        switch ($requiredType) {
            case 'text':
            case 'textarea':
            case 'select':
                $value = $this->sanitizeTextValue($value, $type, $requiredType);
                break;

            case 'color':
                $value = sanitize_hex_color($value);
                break;

            case 'url':
                $value = sanitize_url($value);
                break;

            case 'email':
                $value = sanitize_email($value);
                break;

            case 'integer':
                $value = (int) $value;
                break;

            case 'number':
                $value = (float) $value;
                break;

            case 'boolean':
                $value = $value === '1' ? '1' : '0';
                break;

        }

        return $value;
    }

    /**
     * Helper to sanitize text values. Called by the sanitizeSetting
     * method.
     */
    private function sanitizeTextValue(mixed $value, string $type, string $requiredType): string
    {
        if ($type === 'string') {

            if (in_array($requiredType, ['text', 'select'])) {
                $value = sanitize_text_field($value);
            } elseif ($requiredType === 'textarea') {
                $value = sanitize_textarea_field($value);
            }

        } elseif (in_array($type, ['integer', 'boolean', 'double'])) {
            $value = (string) $value;
        }

        return $value;
    }

    /**
     * Returns all settings for the feature.
     *
     * @param  bool  $fq  Whether to use fully qualified setting names.
     */
    final public function getSettings(bool $fq = true): array
    {
        return $fq ? $this->fqSettings : $this->settings;
    }

    /**
     * Returns a specific setting's current value as retrieved
     * from the database.
     *
     * @param  bool  $fq  Whether to use fully qualified setting name.
     */
    final public function getSetting(string $optionGroup, string $name, bool $fq = false): mixed
    {
        if ($fq) {
            return $this->fqSettings[$optionGroup][$name] ?? null;
        } else {
            return $this->settings[$optionGroup][$name] ?? null;
        }
    }
}
