<?php 

namespace MM\Meros\Traits;

use MM\Meros\Helpers\Fields;
use Illuminate\Support\Str;

trait SettingsManager
{
    protected string $optionGroup;
    protected array  $options             = [];
    protected array  $settings            = [];
    protected string $settingsCapability  = 'manage_options';

    protected function registerSettings(): void
    {
        if ( $this->userSwitchable && !isset( $this->options['enabled'] ) ) {
            $enabledDescription = 'Enables or disables ' . Str::title( $this->name ) . '.';
            $this->options['enabled'] = [
                'label'       => 'Enabled',
                'type'        => 'boolean',
                'description' => $enabledDescription,
                'hasField'    => true,
                'fieldType'   => 'checkbox',
                'default'     => true
            ];
        }

        if ( $this->options === [] ) {
            return;
        }

        add_action( 'admin_init', function() {

            $settingsSectionTitle = Str::title( $this->name ) . ' Options';
            $settingsSectionId    = $this->name . '_options';

            add_settings_section(
                $settingsSectionId,
                $settingsSectionTitle,
                function () {
                    echo 'I am a settings section';
                },
                $this->optionGroup,
                []
            );

            foreach ( $this->options as $option => $schema ) {

                $type             = $schema['type'];
                $default          = $schema['default'] ?? null;
                $description      = $schema['description'] ?? '';
                $hasField         = $schema['hasField'];
                $fieldType        = $schema['fieldType'] ?? null;
                $name             = $schema['name'] ?? $option;
                $required         = $schema['required'] ?? false;
                $sanitizeCallback = $schema['sanitize_callback'] ?? false;

                if ( $type === 'text' || $type === 'textarea' ) {
                    $type = 'string';
                }

                register_setting(
                    $this->optionGroup, $option, [
                        'type'              => $type,
                        'default'           => $default,
                        'description'       => $description,
                        'sanitize_callback' => function( mixed $value ) use( $option, $sanitizeCallback ): mixed {
                            if ( $sanitizeCallback !== false ) {
                                return call_user_func( $sanitizeCallback );
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

                    add_settings_field(
                        "{$this->name}_{$option}",
                        Str::title( $option ),
                        function() use ( $option, $type, $description, $name, $default, $fieldType, $required ) {
                            if ( is_callable( $fieldType ) ) {
                                call_user_func( $fieldType );
                            }
                            else {
                                echo Fields::make( 
                                    $option, $type, $description, $name, $default, $fieldType, $required 
                                );
                            }
                        },
                        $this->optionGroup,
                        $settingsSectionId,
                        [ 'label_for' => $option ]
                    );
                }

                $this->settings[ $option ] = get_option( $option, $default );
            }
        });
    }

    protected function sanitizeOptions(): void
    {
        if ( $this->options === [] ) {
            return;
        }

        $sanitizedOptions = [];

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

    protected function sanitizeOptionSchema( string $option, array $schema ): array
    {
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
            'array'
        ];

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

        $validFieldTypes = [
            'text',
            'textarea',
            'checkbox',
            'select',
            'toggle',
            'radio',
            'color',
            'repeater'
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
    
        // Now sanitize known keys
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

    protected function sanitizeSetting( mixed $value, array $schema ): mixed
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
                    $value = (bool) $value;
                    break;

            }
        }

        else if ( $type === 'array' && $requiredType === 'array' ) {

            if ( is_array( $schema['schema'] ?? null ) ) {

                foreach ( $value as $key => $subValue ) {

                    $value[ $key ] = $this->sanitizeSetting( $subValue, $schema['schema'] );
    
                }

            }

        }
        
        return $value;
    }

    protected function sanitizeTextValue( mixed $value, string $type, string $requiredType ): string
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

    protected function getSettings(): array
    {
        return [];
    }

    protected function updateSettings( array $settings ): void
    {
        // TBC
    }
}