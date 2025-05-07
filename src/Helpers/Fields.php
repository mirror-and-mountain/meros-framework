<?php 

namespace MM\Meros\Helpers;

class Fields
{
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
    
}