<?php 

namespace MM\Meros\Traits;

use MM\Meros\Helpers\Fields;
use Illuminate\Support\Str;

trait SettingsManager
{
    /**
     * The option group for this feature. Determines
     * which settings page in the WP dashboard settings
     * for the feature will appear in.
     *
     * @var string
     */
    protected string $optionGroup;

    /**
     * The given options for the feature. These are translated
     * into settings registered with register_setting.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Existing settings and their current values 
     * loaded via get_option.
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * The capability required to edit this feature's settings
     * in the WP dashboard.
     *
     * @var string
     */
    protected string $settingsCapability = 'manage_options';

    /**
     * Initialises the feature's settings.
     *
     * @return void
     */
    private function initialiseSettings(): void
    {
        $this->setOptionGroup();
        $this->sanitizeOptions();
        $this->setRegisteredSettings();
        $this->registerSettings();
    }

    /**
     * Sets the feature's options group with its given
     * category.
     *
     * @return void
     */
    private function setOptionGroup(): void
    {
        $theme      = app()->make('meros.theme_manager');
        $optionsMap = $theme->getOptionsMap();
        $category   = $this->category;

        $this->optionGroup = array_key_exists( $category, $optionsMap ) 
                ? $optionsMap[ $category ] . '_' . $category
                : $theme->getThemeSlug() . '_settings_miscellaneous';
    }

    /**
     * Retrieves saved settings values for the feature.
     *
     * @return void
     */
    private function setRegisteredSettings(): void
    {
        if ( $this->options === [] ) {
            return;
        }

        foreach ( $this->options as $option => $schema ) {
            $name = $this->name . '_' . $option;
            $this->settings[ $option ] = get_option( $name, $schema['default'] ?? null );
        }
    }

    /**
     * Registers the feature's settings using the options array.
     *
     * @return void
     */
    private function registerSettings(): void
    {
        if ( $this->options === [] || !is_admin() ) {
            return;
        }

        add_action( 'admin_init', function() {

            $settingsSectionTitle = Str::title( Str::replace('_', ' ', $this->name )) . ' Options';
            $settingsSectionTitle = apply_filters( $this->name . '_settings_section_title', $settingsSectionTitle );
            $settingsSectionId    = $this->name . '_options';

            // Add the settings section.
            add_settings_section(
                $settingsSectionId,
                $settingsSectionTitle,
                function () {
                    $content  = $this->author['name']    !== 'unknown' ? "Provided by {$this->author['name']}" : '';
                    $content .= $this->author['link']    !== '' ? " | <a href=\"{$this->author['link']}\" target=\"_blank\">URL</a>" : '';
                    $content .= $this->author['support'] !== '' ? " | <a href=\"{$this->author['support']}\" target=\"_blank\">Support</a>" : '';
                    $content  = apply_filters( $this->name . '_settings_section_content', $content );
                    echo $content;
                },
                $this->optionGroup,
                []
            );

            // Register each option
            foreach ( $this->options as $option => $schema ) {
                $name             = $this->name . '_' . $option;
                $type             = $schema['type'];
                $default          = $schema['default'] ?? null;
                $description      = $schema['description'] ?? '';
                $hasField         = $schema['hasField'];
                $fieldType        = $schema['fieldType'] ?? null;
                $id               = $schema['name'] ?? $this->name . '_' .$option;
                $required         = $schema['required'] ?? false;
                $sanitizeCallback = $schema['sanitize_callback'] ?? false;

                if ( $type === 'text' || $type === 'textarea' ) {
                    $type = 'string';
                }

                register_setting(
                    $this->optionGroup, $name, [
                        'type'              => $type,
                        'default'           => $default,
                        'description'       => $description,
                        'sanitize_callback' => function( mixed $value ) use( $option, $sanitizeCallback ): mixed {
                            if ( $sanitizeCallback !== false ) {
                                return call_user_func( $sanitizeCallback, $value );
                            } else {
                                $schema = $this->options[ $option ];
                                return $this->sanitizeSetting( $value, $schema );
                            }
                        }
                    ]
                );

                if ( $hasField ) {
                    if ( $type === 'array' ) {
                        $fieldType = 'repeater';
                    }

                    // Add a settings field if specified
                    add_settings_field(
                        $name,
                        Str::title( $option ),
                        function() use ( $name, $type, $description, $id, $default, $fieldType, $required ) {
                            // Devs may provide their own callback to render the field.
                            if ( is_callable( $fieldType ) ) {
                                call_user_func( $fieldType );
                            }
                            // Or use the built-in generator.
                            else {
                                echo Fields::make( 
                                    $name, $type, $description, $id, $default, $fieldType, $required 
                                );
                            }
                        },
                        $this->optionGroup,
                        $settingsSectionId,
                        [ 'label_for' => $name ]
                    );
                }
            }
        });
    }

    /**
     * Sanitizes the feature's given options.
     *
     * @return void
     */
    private function sanitizeOptions(): void
    {
        // Create a boolean 'enabled' option if userSwitchable is true
        if ( $this->userSwitchable && !isset( $this->options['enabled'] ) ) {
            $enabledDescription = 'Enable or disable ' . Str::title( Str::replace('_', ' ', $this->name )) . '.';
            $enabledDescription = apply_filters( $this->name . '_user_switch_label', $enabledDescription );
            $this->options['enabled'] = [
                'label'       => 'Enabled',
                'type'        => 'boolean',
                'description' => $enabledDescription,
                'hasField'    => true,
                'fieldType'   => 'checkbox',
                'default'     => '1'
            ];
        }

        if ( $this->options === [] ) {
            return;
        }

        $sanitizedOptions = [];

        // Sanitize each option in the options array
        foreach ( $this->options as $option => $schema ) {
            
            if ( !is_array( $schema ) ) {
                continue;
            }

            if ( !isset( $schema['label'] ) ) {
                $schema['label'] = Str::title( $option );
            }

            if ( !isset( $schema['hasField'] ) ) {
                $schema['hasField'] = true;
            }

            $sanitizedSchema = $this->sanitizeOptionSchema( $option, $schema );
            
            if ( $sanitizedSchema !== [] ) {
                $sanitizedOptions[ $option ] = $sanitizedSchema;
            }
        }

        $this->options = $sanitizedOptions;
    }

    /**
     * Checks, validates and sanitizes a feature option using its schema.
     *
     * @param  string $option
     * @param  array  $schema
     * @return array
     */
    private function sanitizeOptionSchema( string $option, array $schema ): array
    {
        // Allowed data types for the option
        $allowedTypes = [
            'string',
            'text',
            'textarea',
            'select',
            'url',
            'email',
            'hex',
            'boolean',
            'integer',
            'number',
            // 'array' Not currently supported
        ];

        // Valid schema keys for the option
        $validKeys = [
            'label',
            'type',
            'description',
            'hasField',
            'fieldType',
            'name',
            'required',
            'default',
            'schema',
            'options',
            'multi',
            'sanitize_callback'
        ];

        // Valid field types for the fields generator
        $validFieldTypes = [
            'text',
            'textarea',
            'checkbox',
            'select',
            'radio',
            'color',
            // 'repeater' Not currently supported
        ];
    
        $sanitizedSchema = [];
    
        // Auto-set 'type' if missing or invalid
        $defaultValue     = $schema['default'] ?? '_no_default';
        $defaultValueType = $defaultValue !== '_no_default' ? gettype($defaultValue) : null;
    
        $needsType = !isset($schema['type']) || !in_array($schema['type'], $allowedTypes, true);
    
        if ( $needsType ) {
            if ( $defaultValueType && in_array($defaultValueType, $allowedTypes, true) ) {
                $sanitizedSchema['type'] = $defaultValueType;
            } else {
                return []; // cannot determine type, skip
            }
        } else {
            $sanitizedSchema['type'] = $schema['type'];
        }
    
        // Sanitize known schema keys
        foreach ( $schema as $key => $value ) {
    
            if ( !in_array( $key, $validKeys, true ) ) {
                continue; // skip unknown keys
            }
    
            switch ( $key ) {
                case 'label':
                    $sanitizedSchema['label'] = is_string($value) ? $value : Str::title($option);
                    break;

                case 'description':
                case 'name':
                    $sanitizedSchema[ $key ] = is_string($value) ? strip_tags($value) : '';
                    break;

                case 'hasField':
                    $sanitizedSchema['hasField'] = is_bool($value) ? $value : true;
                    break;

                case 'fieldType':
                    if ( in_array( $value, $validFieldTypes ) ) {
                        $sanitizedSchema['fieldType'] = $value;
                    } 
                    else if ( is_callable( $value ) ) {
                        $sanitizedSchema['fieldType'] = $value;
                    }
                    else {
                        $sanitizedSchema['fieldType'] = null;
                    }
                    break;

                case 'required':
                case 'multi':
                    $sanitizedSchema[ $key ] = is_bool($value) ? $value : false;
                    break;
    
                case 'options':
                    $sanitizedOptions = [];
    
                    if ( is_array($value) ) {
                        foreach ( $value as $optValue => $label ) {
                            if ( is_string($optValue) && is_string($label) ) {
                                $sanitizedOptions[$optValue] = $label;
                            }
                        }
                    }
    
                    if ( $sanitizedOptions !== [] ) {
                        $sanitizedSchema['options'] = $sanitizedOptions;
                    }
                    break;
    
                case 'schema':
                    if ( $sanitizedSchema['type'] === 'array' && is_array($value) ) {
                        $subSchema = $this->sanitizeOptionSchema($option, $value);
                        if ( $subSchema === [] ) {
                            return []; // invalid subschema, bail
                        }
                        $sanitizedSchema['schema'] = $subSchema;
                    }
                    break;
    
                case 'default':
                    $sanitizedSchema['default'] = $value;
                    break;

                case 'sanitize_callback':
                    $sanitizedSchema['sanitize_callback'] = is_callable($value) ? $value : null;
                    break;
            }
        }
    
        return $sanitizedSchema;
    }

    /**
     * Callback to sanitize a setting when modified in the WP dashboard.
     *
     * @param  mixed $value
     * @param  array $schema
     * @return mixed
     */
    private function sanitizeSetting( mixed $value, array $schema ): mixed
    {
        $requiredType     = $schema['type'];
        $required         = $schema['required'] ?? false;
        $type             = gettype( $value );
        $shouldCastScalar = $type !== 'array' && $requiredType !== 'array';

        if ( $shouldCastScalar ) {
            
            switch ( $requiredType ) {

                case 'string':
                case 'text':
                case 'textarea':
                case 'select':
                    $value = $this->sanitizeTextValue( $value, $type, $requiredType );
                    break;

                case 'hex':
                    $value = sanitize_hex_color( $value );
                    break;

                case 'url':
                    $value = sanitize_url( $value );
                    break;

                case 'email':
                    $value = sanitize_email( $value );
                    break;
                
                case 'integer':
                    $value = (int) $value;
                    break;

                case 'number':
                    $value = (float) $value;
                    break;

                case 'bool':
                case 'boolean':
                    $value = $value === '1' ? '1' : '0';
                    break;

            }
        }

        // Not currently supported
        else if ( $type === 'array' && $requiredType === 'array' ) {

            if ( is_array( $schema['schema'] ?? null ) ) {

                foreach ( $value as $key => $subValue ) {

                    $value[ $key ] = $this->sanitizeSetting( $subValue, $schema['schema'] );
    
                }

            }

        }
        
        return $value;
    }

    /**
     * Helper to sanitize text values. Called by the sanitizeSetting
     * method.
     *
     * @param  mixed  $value
     * @param  string $type
     * @param  string $requiredType
     * @return string
     */
    private function sanitizeTextValue( mixed $value, string $type, string $requiredType ): string
    {
        if ( $type === 'string' ) {

            if ( in_array( $requiredType, ['text', 'string', 'select'] ) ) {
                $value = sanitize_text_field( $value );
            }

            elseif ( $requiredType === 'textarea' ) {
                $value = sanitize_textarea_field( $value );
            }

        }

        elseif ( in_array( $type, ['integer', 'boolean', 'double'] ) ) {

            $value = (string) $value;

        }

        return $value;
    }

    /**
     * Returns the feature's current settings as retrieved from the
     * database. Will return an empty array if no settings have been
     * defined.
     *
     * @return array
     */
    final public function getSettings(): array
    {
        return $this->settings;
    }
}