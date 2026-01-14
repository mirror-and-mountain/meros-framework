<?php

namespace MM\Meros\Helpers;

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

            case 'button':
                $html .= self::makeButton($name, $id);
                break;

            case 'toggle':
                $html .= self::makeToggle($name, $id);
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

    /**
     * Makes a button field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param string $labelEnabled The label when the button is in enabled state.
     * @param string $labelDisabled The label when the button is in disabled state.
     * @return string The generated HTML for the button.
     */
    private static function makeButton(
        string $name,
        string $id = '',
        string $labelEnabled = 'Enabled',
        string $labelDisabled = 'Enable'
    ): string {
        $stored_value = get_option($name);
        $isEnabled = (bool) $stored_value;

        $label = $isEnabled ? $labelEnabled : $labelDisabled;
        $button_id = $id !== '' ? $id : $name;
        $nonce = wp_create_nonce('mm_meros_toggle_' . $name);

        return sprintf(
            '<button
                type="button"
                id="%s"
                title="%s"
                class="button button-primary meros-toggle-btn"
                data-option="%s"
                data-value="%s"
                data-nonce="%s"
            >%s</button>',
            esc_attr($button_id),
            esc_attr($isEnabled ? 'Disable' : 'Enable'),
            esc_attr($name),
            esc_attr((bool) $stored_value ? '1' : '0'),
            esc_attr($nonce),
            esc_html($label)
        );
    }

    /**
     * Makes a toggle switch field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param string $labelEnabled The label when the toggle is in enabled state.
     * @param string $labelDisabled The label when the toggle is in disabled state.
     * @return string The generated HTML for the toggle switch.
     */
    private static function makeToggle(
        string $name,
        string $id = '',
        string $labelEnabled = 'Enabled',
        string $labelDisabled = 'Disabled'
    ): string {
        $isEnabled = (bool) get_option($name);

        $toggle_id = $id !== '' ? $id : $name . '_toggle';
        $nonce = wp_create_nonce('mm_meros_toggle_' . $name);

        return sprintf(
            '<button
                type="button"
                id="%s"
                class="meros-toggle-switch %s"
                role="switch"
                aria-checked="%s"
                data-option="%s"
                data-nonce="%s"
            >
                <span class="meros-toggle-track">
                    <span class="meros-toggle-thumb"></span>
                </span>
                <span class="meros-toggle-label">%s</span>
            </button>',
            esc_attr($toggle_id),
            $isEnabled ? 'is-enabled' : 'is-disabled',
            $isEnabled ? 'true' : 'false',
            esc_attr($name),
            esc_attr($nonce),
            esc_html($isEnabled ? $labelEnabled : $labelDisabled)
        );
    }
}
