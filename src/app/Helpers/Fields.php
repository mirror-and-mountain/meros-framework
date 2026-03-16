<?php

namespace MM\Meros\App\Helpers;

/**
 * A utility to generate fields of varying types for use
 * in Wordpress settings pages or metaboxes.
 */
class Fields {
    /**
     * Makes a field.
     *
     * @param string $name The name of the field.
     * @param string $valueType The data type of the field's value.
     * @param mixed $default The default value for the field.
     * @param string $fieldType The type of field to generate.
     * @param string $id An optional ID for the field.
     * @param bool $required Whether the field is required.
     * @param array $options An array of options for select fields.
     * @return string The generated HTML for the field.
     * 
     */
    public static function make(
        string $name,
        string $valueType,
        mixed $default,
        string $fieldType = '',
        string $id = '',
        bool $required = false,
        array $options = []
    ): string {
        $html = '';
        $id = $id !== '' ? $id : $name;
        $value = get_option($name, $default);
        $type = $fieldType !== '' ? $fieldType : $valueType;

        // Set html for the given field type
        switch ($type) {
            case 'boolean':
            case 'checkbox':
                $html .= self::makeCheckbox($name, $id, $default);
                break;

            case 'text':
            case 'number':
            case 'email':
            case 'url':
            case 'password':
            case 'color':
            case 'date':
                $html .= self::makeInput($name, $id, $type, $value, $required);
                break;

            case 'select':
            case 'multi_select':
                $multiple = $type === 'multi_select' ? true : false;
                $html .= self::makeSelect($name, $id, $value, $options, $multiple);
                break;

            case 'textarea':
                $html .= self::makeTextarea($name, $id, $value, $required);
                break;
        }

        return $html;
    }

    /**
     * Makes a checkbox field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param mixed $value The value to compare for checked state.
     * @return string The generated HTML for the checkbox.
     */
    private static function makeCheckbox(
        string $name,
        string $id = '',
        mixed $value = null
    ): string {
        $checked = checked(get_option($name, $value), '1', false);

        $html = '<input type="hidden" name="' . esc_attr($name) . '" value="0" />';
        $html .= '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . $checked . ' />';

        return $html;
    }

    /**
     * Makes an input field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param string $type The type of input field.
     * @param mixed $value The value of the field.
     * @param bool $required Whether the field is required.
     * @return string The generated HTML for the input field.
     */
    private static function makeInput(
        string $name,
        string $id = '',
        string $type = 'text',
        mixed $value = null,
        bool $required = false
    ): string {
        $html = '<input type="' . $type . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"';

        if ($required) {
            $html .= ' required';
        }

        $html .= ' />';

        return $html;
    }

    /**
     * Makes a textarea field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param mixed $value The value of the field.
     * @param bool $required Whether the field is required.
     * @return string The generated HTML for the textarea field.
     */
    private static function makeTextarea(
        string $name,
        string $id = '',
        mixed $value = null,
        bool $required = false
    ): string {
        $html = '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"';

        if ($required) {
            $html .= ' required';
        }

        $html .= '>' . esc_textarea($value) . '</textarea>';

        return $html;
    }

    /**
     * Makes a select field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param mixed $value The value of the field.
     * @param array $options An array of options for the select field.
     * @param bool $multiple Whether the select allows multiple selections.
     * @return string The generated HTML for the select field.
     */
    private static function makeSelect(
        string $name,
        string $id = '',
        mixed $value = null,
        array $options = [],
        bool $multiple = false
    ): string {
        $html = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . ($multiple ? ' multiple' : '') . '>';

        foreach ($options as $key => $label) {
            $selected = selected($value, $key, false);
            $html .= '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
