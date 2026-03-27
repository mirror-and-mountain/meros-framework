<?php

namespace MM\Meros\App\Support\Admin;

use Illuminate\Support\Str;

/**
 * A utility to generate fields of varying types for use
 * in Wordpress settings pages or metaboxes.
 */
class Field {
    /**
     * Makes a field.
     *
     * @param string $name The name of the field.
     * @param string $type The type of field to make e.g. 'text'
     * @param mixed  $value The value for the field.
     * @param string $id An optional ID for the field.
     * @param bool   $required Whether the field is required. Defaults to false.
     * @param bool   $disabled Whether the field is disabled. Defaults to false.
     * @param array  $options An array of options for select fields.
     * @param string $ajaxAction An ajax action for rendering a field with ajax functionality.
     * @param array  $attributes Additional data attributes for the field
     * @param string $nonce An optional nonce for the field. If not provided, a nonce will be generated using the ajax action and field name.
     * 
     * @return string The generated HTML for the field.
     */
    public static function make(
        string $name,
        string $type,
        mixed  $value = null,
        string $id = '',
        bool   $required = false,
        bool   $disabled = false,
        array  $options = [],
        string $ajaxAction = '',
        array  $attributes = [],
        string $nonce = ''
    ): string {
        $html = '';
        $id   = $id !== '' ? $id : $name;

        // Set html for the given field type
        switch ($type) {
            case 'boolean':
            case 'checkbox':
                $html .= self::makeCheckbox($name, $id, $value, $disabled, $ajaxAction, $attributes, $nonce);
                break;

            case 'text':
            case 'number':
            case 'email':
            case 'url':
            case 'password':
            case 'color':
            case 'date':
                $html .= self::makeInput($name, $id, $type, $value, $required, $disabled);
                break;

            case 'select':
            case 'multi_select':
                $multiple = $type === 'multi_select' ? true : false;
                $html .= self::makeSelect($name, $id, $value, $options, $multiple, $disabled);
                break;

            case 'textarea':
                $html .= self::makeTextarea($name, $id, $value, $required, $disabled);
                break;
        }

        return $html;
    }

     /**
     * Makes an action button for use with ajax.
     * 
     * @param string  $action The action associated with the button.
     * @param string  $id The id attribute for the button.
     * @param string  $label The label for the button.
     * @param bool    $shortLabel Whether to use a short label for the button.
     * @param array   $attributes Additional data attributes for the button.
     * @param string  $nonce An optional nonce for the button. If not provided, a nonce will be generated using the action and id.
     * 
     * @return string The generated HTML for the button.
     */
    public static function makeButton(
        string $action,
        string $id,
        string $label,
        bool   $shortLabel = true,
        array  $attributes = [],
        string $nonce = ''
    ): string {
        $nonce = $nonce !== '' ? wp_create_nonce($nonce) : wp_create_nonce($action . '_' . $id);

        return sprintf(
            '<div id="%s" class="meros-action-btn-wrapper">
                <button
                    type="button"
                    id="%s"
                    title="%s"
                    class="button button-primary meros-action-btn"
                    data-action="%s"
                    data-nonce="%s"
                    %s
                >
                    <span class="meros-action-btn-label">%s</span>
                </button>
            </div>',
            esc_attr($action . '_' . $id . '_wrapper'),
            esc_attr($action . '_' . $id),
            esc_attr($label),
            esc_attr($action),
            esc_attr($nonce),
            self::formatDataAttributes($attributes),
            esc_html($shortLabel ? Str::before($label, ' ') : $label)
        );
    }

    /**
     * Formats an array of data attributes into a string for inclusion in an HTML tag.
     * 
     * @param array $attributes An associative array of attributes where the key is the attribute name and the value is the attribute value.
     * @return string The formatted string of attributes.
     */
    private static function formatDataAttributes(array $attributes): string {
        $formatted = '';

        foreach ($attributes as $key => $value) {
            $formatted .= sprintf('data-%s="%s" ', esc_attr($key), esc_attr($value));
        }

        return $formatted;
    }

    /**
     * Makes a checkbox / toggle switch field.
     * 
     * @param string  $name The name of the field.
     * @param string  $id An optional ID for the field.
     * @param mixed   $value The value to compare for checked state.
     * @param bool    $disabled Whether the checkbox should be disabled.
     * @param string  $ajaxAction An ajax action for handling a toggle switch.
     * @param array   $attributes Additional data attributes for the checkbox.
     * @param string  $nonce An optional nonce for the checkbox. If not provided, a nonce will be generated using the ajax action and name.
     * 
     * @return string The generated HTML for the checkbox / toggle switch.
     */
    private static function makeCheckbox(
        string $name,
        string $id = '',
        mixed  $value = null,
        bool   $disabled = false,
        string $ajaxAction = '',
        array  $attributes = [],
        string $nonce = ''
    ): string {
        $checked = checked($value, true, false);

        if ($ajaxAction !== '') {
            $nonce = $nonce !== '' ? wp_create_nonce($nonce) : wp_create_nonce($ajaxAction . '_' . $name);

            return sprintf(
                '<button
                    type="button"
                    id="%s"
                    class="meros-toggle-switch meros-settings-field %s"
                    role="switch"
                    %s
                    aria-checked="%s"
                    data-action="%s"
                    data-nonce="%s"
                    %s
                >
                    <span class="meros-toggle-track">
                        <span class="meros-toggle-thumb"></span>
                    </span>
                    <span class="meros-toggle-label">%s</span>
                </button>',
                esc_attr($id !== '' ? $id : $name),
                $checked ? 'checked' : 'not-checked',
                $disabled ? 'disabled' : '',
                $checked ? 'true' : 'false',
                esc_attr($ajaxAction),
                esc_attr($nonce),
                self::formatDataAttributes($attributes),
                esc_html($checked ? 'Enabled' : 'Disabled') // Label
            );
        }

        $html = '<input type="hidden" class="meros-settings-field" name="' . esc_attr($name) . '" value="0" />';
        $html .= '<input type="checkbox" class="meros-settings-field" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . $checked . ($disabled ? ' disabled' : '') . ' />';

        return $html;
    }

    /**
     * Makes an input field.
     * 
     * @param string $name The name of the field.
     * @param string $id An optional ID for the field.
     * @param string $type The type of input field.
     * @param mixed  $value The value of the field.
     * @param bool   $required Whether the field is required.
     * @param bool   $disabled Whether the field is disabled.
     * @return string The generated HTML for the input field.
     */
    private static function makeInput(
        string $name,
        string $id = '',
        string $type = 'text',
        mixed  $value = null,
        bool   $required = false,
        bool   $disabled = false
    ): string {
        $html = '<input type="' . $type . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"';

        if ($required) {
            $html .= ' required';
        }

        if ($disabled) {
            $html .= ' disabled';
        }

        $html .= ' class="meros-settings-field" />';

        return $html;
    }

    /**
     * Makes a textarea field.
     * 
     * @param string  $name The name of the field.
     * @param string  $id An optional ID for the field.
     * @param mixed   $value The value of the field.
     * @param bool    $required Whether the field is required.
     * @param bool    $disabled Whether the field is disabled.
     * @return string The generated HTML for the textarea field.
     */
    private static function makeTextarea(
        string $name,
        string $id = '',
        mixed  $value = null,
        bool   $required = false,
        bool   $disabled = false
    ): string {
        $html = '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"';

        if ($required) {
            $html .= ' required';
        }

        if ($disabled) {
            $html .= ' disabled';
        }

        $html .= ' class="meros-settings-field">' . esc_textarea($value) . '</textarea>';

        return $html;
    }

    /**
     * Makes a select field.
     * 
     * @param string  $name The name of the field.
     * @param string  $id An optional ID for the field.
     * @param mixed   $value The value of the field.
     * @param array   $options An array of options for the select field.
     * @param bool    $multiple Whether the select allows multiple selections.
     * @param bool    $disabled Whether the select is disabled.
     * @return string The generated HTML for the select field.
     */
    private static function makeSelect(
        string $name,
        string $id = '',
        mixed  $value = null,
        array  $options = [],
        bool   $multiple = false,
        bool   $disabled = false
    ): string {
        $html = '<select id="' . esc_attr($id) . '" class="meros-settings-field" name="' . esc_attr($name) . '"' . ($multiple ? ' multiple' : '') . ($disabled ? ' disabled' : '') . '>';

        foreach ($options as $key => $label) {
            $selected = selected($value, $key, false);
            $html .= '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
