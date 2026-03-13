<?php

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Support\Str;

use MM\Meros\Helpers\Fields;
use MM\Meros\App\Facades\AdminManager;

trait HasSettings {
    /**
     * The given settings for the feature. These are translated
     * into registered settings with wp's register_setting function.
     */
    protected array $fqSettings = [];
    protected array $settings = [];

    /**
     * The name of the setting used to enable/disable
     * the feature if it is user switchable.
     * 
     * @var string
     */
    private string $enabledSettingName = '';

    /**
     * The capability required to edit this feature's settings
     * in the WP dashboard.
     * 
     * @var string
     */
    protected string $settingsCapability = 'manage_options';

    /**
     * The sections for the feature's settings.
     * 
     * @var array
     */
    protected array $settingsSections = [];

    /**
     * Valid option types for settings.
     * 
     * @var array
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
     * 
     * @var array
     */
    protected array $defaultSettingConfig = [
        'type'        => 'text',
        'description' => '',
        'hasField'    => true,
        'fieldType'   => '',
        'default'     => null,
        'required'    => false,
        'options'     => [],
        'sanitize_callback' => null,
    ];

    /**
     * Mapping of setting types to field types.
     * 
     * @var array
     */
    protected array $settingFieldTypes = [
        'text'         => 'text',
        'url'          => 'url',
        'email'        => 'email',
        'password'     => 'password',
        'date'         => 'date',
        'textarea'     => 'textarea',
        'number'       => 'number',
        'integer'      => 'number',
        'boolean'      => ['checkbox', 'toggle', 'button'],
        'select'       => 'select',
        'multi_select' => 'select',
        'color'        => 'color',
    ];

    /**
     * Creates an 'Enabled' switch for the given package, block or asset
     * in WP Admin. This is essentially a shortcut for adding a
     * boolean setting via the addSetting method.
     *
     * @param string  $type           The type of item being switched (e.g. 'package', 'block', 'asset').
     * @param string  $name           The name of the item being switched.
     * @param string  $page           The slug of the settings page to add the switch to.
     * @param string  $tab            The slug of the settings tab to add the switch to.
     * @param string  $description    A description for the switch setting.
     * @param boolean $isExperimental Whether the item is experimental.
     * @param boolean $linkToPackage  Whether to add a link to the item's parent package.
     * @return string|boolean         The name of the registered setting or false if registration failed.
     */
    private function createSwitch(
        string $type,
        string $name,
        string $page,
        string $tab,
        string $description  = '',
        bool $isExperimental = false,
        bool $linkToPackage  = true
    ) {
        // Check type is valid
        if (!in_array($type, ['package', 'block', 'asset'])) {
            return false;
        }

        // Slug the name
        $name = Str::slug($name, '_');

        // Format the label
        $label = 'Enable ' . $this->name;

        // Format the description
        $description = $description !== ''
            ? $description
            : "Enable or disable the {$this->name} {$type}.";

        // Set config for the setting
        $config = [
            'type'        => 'boolean',
            'description' => $description,
            'default'     => '1',
            'hasField'    => true
        ];

        // Use Ajax toggle for package switches
        if ($type === 'package') {
            $config['fieldType'] = 'toggle';

            if ($isExperimental) {
                $tab = 'experimental-features';
            }
        }

        $result = $this->addSetting(
            "enable_{$name}_{$type}", 
            $label, 
            $page, 
            $tab, 
            '', 
            '', 
            $config,
            $linkToPackage,
            $isExperimental
        );

        if ($type === 'package' && is_string($result)) {
            $this->enabledSettingName = $result;
        }

        return $result;
    }

    /**
     * Adds a setting to the specified settings page.
     * This method will also create a settings section.
     *
     * @param string  $name           The name of the setting. This will be used to generate the option name in the database.
     * @param string  $label          The label for the setting field in the WP dashboard.
     * @param string  $page           The slug of the settings page to add the setting to.
     * @param string  $tab            The tab of the settings page to add the setting to.
     * @param string  $sectionId      The ID of the settings section to add the setting to. If not provided, a section will be created for the package.
     * @param string  $sectionTitle   The title of the settings section. Not really used in the UI, so defaults to ''.
     * @param array   $config         Configuration for the setting.
     * @param boolean $linkToPackage  Whether to link the setting to its parent package.
     * @param boolean $isExperimental Whether the functionality the setting is for is experimental.
     * @return string|boolean         The name of the registered setting or false if registration failed.
     */
    protected function addSetting(
        string $name,
        string $label,
        string $page = 'theme_settings',
        string $tab  = '',
        string $sectionId = '',
        string $sectionTitle = '',
        array $config = [
            'type'        => 'text',
            'description' => '',
            'hasField'    => true,
            'fieldType'   => '',
            'default'     => null,
            'required'    => false,
            'options'     => [],
            'sanitize_callback' => null,
        ],
        bool $linkToPackage  = true,
        bool $isExperimental = false
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

        // Check the page exists
        $optionPageSlug = '';
        $optionsPages = AdminManager::getOptionsPages();
        if (! array_key_exists($page, $optionsPages)) {
            return false;
        } else {
            $optionPageSlug = $optionsPages[$page]['menu_slug'];
        }

        // Validate tab exists. If not, create it.
        $tab = $tab === '' ? 'miscellaneous' : Str::slug($tab, '_');

        if (! in_array($tab, $optionsPages[$page]['tabs'])) {
            AdminManager::addOptionsPageTab($page, $tab);
        }

        // Ensure hasField is set
        if (! isset($config['hasField'])) {
            $config['hasField'] = true;
        }

        // Sanitize the config
        $sanitizedConfig = [
            'type'              => is_string($config['type']) ? $config['type'] : 'text',
            'description'       => is_string($config['description']) ? $config['description'] : '',
            'hasField'          => is_callable($config['hasField']) ? $config['hasField'] : (bool) $config['hasField'],
            'fieldType'         => is_string($config['fieldType']) ? $config['fieldType'] : '',
            'default'           => $config['default'] ?? null,
            'required'          => isset($config['required']) ? (bool) $config['required'] : false,
            'options'           => is_array($config['options']) ? $config['options'] : [],
            'sanitize_callback' => is_callable($config['sanitize_callback']) ? $config['sanitize_callback'] : null,
        ];

        // Generate section ID if not provided
        if ($sectionId === '') {
            $sectionId = Str::slug($this->authorName, '_') . '_' . $page . '_' . $tab . '_section';
        }

        // Set the option group as a combination of page and tab
        $optionGroup = $optionPageSlug . '_' . $tab;

        // Add settings section
        $sectionId = $this->addSettingsSection($sectionId, $sectionTitle, $optionPageSlug, $tab, $name);

        // Mark as experimental in the label if specified
        if ($this->experimental) {
            $isExperimental = true;
        }

        if ($isExperimental) {
            $label .= ' (Experimental)';
        }

        // Register setting and return option name
        return $this->registerSetting(
            $name,
            $label,
            $optionGroup,
            $sectionId,
            $sanitizedConfig,
            $linkToPackage,
            $isExperimental
        );
    }

    /**
     * Registers a setting with Wordpress and retrieves its
     * current value.
     *
     * @param string  $name           The name of the setting. This will be used to generate the option name in the database.
     * @param string  $label          The label for the setting field in the WP dashboard.
     * @param string  $optionGroup    The option group for the setting, usually in the format 'page_tab'.
     * @param string  $sectionId      The ID of the settings section to add the setting to.
     * @param array   $config         Configuration for the setting.
     * @param boolean $linkToPackage  Whether to link the setting to its parent package in the WP dashboard.
     * @param boolean $isExperimental Whether the functionality the setting is for is experimental.
     * @return string
     */
    private function registerSetting(
        string $name,
        string $label,
        string $optionGroup,
        string $sectionId,
        array  $config,
        bool   $linkToPackage = true,
        bool   $isExperimental = false
    ): string {
        $optionName = '_meros_' . $this->slug . '_' . $name;

        $current = get_option($optionName, $config['default'] ?? null);
        $this->fqSettings[$optionGroup][$optionName] = $current;
        $this->settings[$optionGroup][$name] = $current;

        add_action('admin_init', function () use (
            $optionName,
            $label,
            $optionGroup,
            $sectionId,
            $config,
            $linkToPackage,
            $isExperimental
        ) {
            // Register the setting with WordPress
            register_setting(
                $optionGroup,
                $optionName,
                [
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

            // Create a setting field.
            if ($config['hasField']) {
                // Add a settings field if specified
                $type = $config['type'] === 'integer' ? 'number' : $config['type'];
                $label = '<label id="' . esc_attr($optionName) . '" for="' . esc_attr($optionName) . '" class="meros-settings-label">' . esc_html($label);

                if ($linkToPackage && $this->featureEnabledSettingName !== '') {
                    $featuresTab = $isExperimental ? 'experimental_features' : 'features';

                    $featureUrl = admin_url('options-general.php?page=theme_features&tab=' . $featuresTab . '#' . $this->featureEnabledSettingName);
                    $label .= " | <a style=\"font-weight:400;\" href=\"{$featureUrl}\">View Feature</a></label>";
                } else {
                    $label .= '</label>';
                }

                $description = $config['description'] !== ''
                    ? '<p class="description">' . esc_html($config['description']) . '</p>'
                    : '';

                // Add migration controls if allowed
                // if ($this->hasMigrations) {
                //     $migrations = Theme::getMigrations($this->hookPrefix);
                //     $hasInstalledInitialType = false;
                //     $hasUpdates = false;

                //     $lastMigration = MerosMigration::where(
                //             'source', $this->hookPrefix)
                //             ->latest('batch_id')
                //             ->first();

                //     $lastMigrationTime = $lastMigration ? $lastMigration->created_at->format('d-m-Y H:i:s') : false;

                //     foreach($migrations as $migration) {
                //         $migrationRecord = MerosMigration::where('slug', $migration['slug'])->first();
                //         $isInitialType = Str::startsWith($migration['slug'], 'create_');
                        
                //         if ($migrationRecord !== null && $isInitialType) {
                //             $hasInstalledInitialType = true;
                //             continue;
                //         } 
                        
                //         if ($migrationRecord === null) {
                //             $hasUpdates = true;
                //         }
                //     }

                //     $btnLabel = 'Up To Date';
                //     if ($hasInstalledInitialType && $hasUpdates) {
                //         $btnLabel = 'Update';
                //     } else if (!$hasInstalledInitialType) {
                //         $btnLabel = 'Install';
                //     }

                //     $description .= '<p class="description">';
                //     if ($lastMigrationTime) {
                //         $description .= "Last updated: {$lastMigrationTime}. ";
                //     }

                //     if (Theme::onlyAllowsMigrationsFromCli()) {
                //         $description .= "Please run migrations via WP CLI.";
                //     } // here move logic above to database trait and call something like 'hasUpdates()'
                // }

                add_settings_field(
                    $optionName,
                    $label . $description,
                    function () use ($optionName, $config, $type) {
                        $html = '';
                        if (is_callable($config['hasField'])) {
                            $html = call_user_func($config['hasField']);
                        } else {
                            $html = Fields::make(
                                $optionName,
                                $type,
                                $config['default'],
                                $config['fieldType'],
                                $optionName,
                                $config['required'],
                                $config['options']
                            );
                        }

                        echo $html;
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
     *
     * @param string  $id            The ID of the settings section
     * @param string  $title         The title of the settings section. Not really used in the UI.
     * @param string  $page          The page to add the settings section to.
     * @param string  $tab           The page tab to add the settings section to.
     * @param boolean $linkToPackage Whether to show a link to the setting's parent package.
     * @return string The ID of the added section.
     */
    protected function addSettingsSection(
        string $id,
        string $title,
        string $page,
        string $tab,
        string $settingName
    ): string {
        if (isset($this->settingsSections[$id])) {
            $this->settingsSections[$id]['settings'][] = $settingName;
        } else {
            $this->settingsSections[$id] = [
                'title'    => $title,
                'page'     => $page,
                'tab'      => $tab,
                'settings' => [$settingName],
            ];
        }

        // Check whether the section is already registered
        if (AdminManager::getRegisteredSettingsSection($page, $tab, $id) !== null) {
            // Update the section with this setting.
            AdminManager::updateSettingsSection($id, $settingName);
            return $id;
        }

        // Register the settings section
        add_action('admin_init', function () use ($id, $title, $page, $tab) {
            add_settings_section(
                $id,
                $title,
                function () use ($id) {
                    $content = '';
                    if ($this->authorName !== 'Unknown') {
                        $content = "<h3 id=\"{$id}\" style=\"margin-bottom: -8px;\">Provided by {$this->authorName}</h3><p style=\"margin-bottom: -8px;\">";
                        $content .= $this->authorUrl !== '' ? "<a href=\"{$this->authorUrl}\" target=\"_blank\">Website</a>" : '';
                        $content .= $this->authorSupportUrl !== '' ? " | <a href=\"{$this->authorSupportUrl}\" target=\"_blank\">Support</a>" : '';
                        $content .= '</p>';
                        $content = apply_filters($this->hookPrefix . '_' . $id . '_content', $content, $content);
                    }
                    echo $content;
                },
                $page . '_' . $tab,
                []
            );
        }, 5);

        AdminManager::registerSettingsSection($id, $this->settingsSections[$id]);
        return $id;
    }

    /**
     * Callback to sanitize a setting when modified in the WP dashboard.
     *
     * @param mixed $value
     * @param array $config
     * @return mixed
     */
    private function sanitizeSetting(mixed $value, array $config): mixed {
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
     *
     * @param mixed  $value
     * @param string $type
     * @param string $requiredType
     * @return string
     */
    private function sanitizeTextValue(mixed $value, string $type, string $requiredType): string {
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
     * @param bool $fq Whether to use fully qualified setting names.
     */
    final public function getSettings(bool $fq = true): array {
        return $fq ? $this->fqSettings : $this->settings;
    }

    /**
     * Returns a specific setting's current value as retrieved
     * from the database.
     *
     * @param string $optionGroup
     * @param string $name
     * @param bool   $fq Whether to use fully qualified setting name.
     * @return mixed
     */
    final public function getSetting(string $optionGroup, string $name, bool $fq = false): mixed {
        if ($fq) {
            return $this->fqSettings[$optionGroup]['_m_' . $name] ?? null;
        } else {
            return $this->settings[$optionGroup][$name] ?? null;
        }
    }
}
