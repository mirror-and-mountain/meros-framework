<?php 

namespace MM\Meros\Helpers;

class Fields
{
    public static function make( 
        string $option, 
        string $valueType, 
        string $description, 
        string $name, 
        ?mixed $default, 
        ?string $fieldType,
        bool $required = false
    ): string
    {
        $html  = '';
        $value = get_option( $option, $default );

        if ( !isset( $fieldType ) ) {

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

                default:
                    $fieldType = false;
                    break;
            }

        }

        if ( !$fieldType ) {
            return $html;
        }

        switch ( $fieldType ) {

            case 'text':
            case 'checkbox':
            case 'number':
            case 'email':
            case 'url':
                $html .= '<input id="' . esc_attr( $name ) . '" 
                name="' . esc_attr( $name ) . '" 
                type="' . $fieldType . '" 
                value="' . esc_attr( $value ) . '"';

                if ( $fieldType === 'checkbox' && $value === '1' ) {
                    $html .= ' checked';
                }

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
    
}