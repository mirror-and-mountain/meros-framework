<?php 

namespace MM\Meros\Helpers;

/**
 * A utility to generate fields of varying types for use
 * in Wordpress settings pages.
 */
class Fields
{
    /**
     * Makes a field based on the given option.
     *
     * @param  string      $option
     * @param  string      $valueType
     * @param  string      $description
     * @param  string      $id
     * @param  mixed       $default
     * @param  string|null $fieldType
     * @param  bool        $required
     * @return string
     */
    public static function make( 
        string $option, 
        string $valueType, 
        string $description, 
        string $id, 
        mixed $default, 
        ?string $fieldType,
        bool $required = false
    ): string
    {
        $html  = '';
        $value = get_option( $option, $default );

        /**
         * If a field type isn't given, try and determine a type based on
         * the option's data type.
         */
        if ( !isset( $fieldType ) ) {
            $fieldType = self::getFieldType( $valueType );
        }

        if ( !$fieldType ) {
            return $html;
        }

        // Set html for the given field type
        switch ( $fieldType ) {
            
            case 'checkbox':
                $checked = checked( $value, '1', false );
                $html .= '<input type="hidden" name="' . esc_attr( $option ) . '" value="0" />';
                $html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option ) . '" value="1" ' . $checked . ' />';
                break;                

            case 'text':
            case 'number':
            case 'email':
            case 'url':
                $html .= '<input type="' . $fieldType . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '"';

                if ( $required ) {
                    $html .= ' required';
                }

                $html .= ' />';
                break;
        }

        if ( $description !== '' ) {
            $html .= '<span>' . esc_html( $description ) . '</span>';
        }

        return $html;
    }

    /**
     * Attempts to determine which field type should be used
     * based on an option's value[data] type.
     *
     * @param  string      $valueType
     * @return string|bool
     */
    private static function getFieldType( string $valueType ): string|bool
    {
        $fieldType = false;
        switch ( $valueType ) {
            case 'string':
            case 'text':
                $fieldType = 'text';
                break;
            
            case 'textarea':
                $fieldType = 'textarea';
                break;
            
            case 'select':
                $fieldType = 'select';
                break;
            
            case 'url':
                $fieldType = 'url';
                break;
            
            case 'email':
                $fieldType = 'email';
                break;
            
            case 'hex':
                $fieldType = 'color';
                break;
            
            case 'boolean':
                $fieldType = 'checkbox';
                break;
            
            case 'integer':
            case 'number':
                $fieldType = 'number';
                break;
        }

        return $fieldType;
    }
}