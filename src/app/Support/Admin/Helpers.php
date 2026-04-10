<?php 

namespace MM\Meros\App\Support\Admin;

class Helpers {
    /**
     * Sanitizes a value based on the required type. 
     *
     * @param mixed  $value
     * @param string $requiredType
     * 
     * @return mixed The sanitized value
     */
    public static function sanitize(mixed $value, string $requiredType): mixed {
        $type = gettype($value);

        switch ($requiredType) {
            case 'string':
            case 'text':
            case 'tel':
            case 'password':
            case 'date':
            case 'textarea':
            case 'select':
                $value = self::sanitizeTextValue($value, $type, $requiredType);
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
            case 'checkbox':
                $value = (bool) $value ? '1' : '0';
                break;
        }

        return $value;
    }

    /**
     * Helper to sanitize text values. Called by the sanitizeValue method.
     *
     * @param mixed  $value
     * @param string $type
     * @param string $requiredType
     * 
     * @return string The sanitized value
     */
    private static function sanitizeTextValue(mixed $value, string $type, string $requiredType): string {
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
}