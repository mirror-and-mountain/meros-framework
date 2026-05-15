<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

trait HasSanitizer {
    /**
     * Used to cache the existing value of the item during sanitization
     *
     * @var array|null
     */
    protected ?array $cachedExisting = null;

    /**
     * Sets the sanitize callback for the setting.
     *
     * @param callable|Closure $callback A callable or method reference for sanitizing the setting's value.
     *
     * @return self
     */
    public function sanitizer(callable|Closure $callback): self {
        $this->args['sanitize_callback'] = $this->convertToClosure($callback);

        return $this;
    }

    /**
     * The default sanitizer for settings values.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    final public function sanitize(mixed $value): mixed {
        if ($this->type === 'array') {
            return $this->sanitizeArray(is_array($value) ? $value : []);
        }

        if ($this->type === 'object') {
            return $this->sanitizeObject($value);
        }

        $requiredType = $this->type;

        $map = [
            'text'      => 'string',
            'textarea'  => 'string',
            'select'    => 'string',
            'email'     => 'string',
            'url'       => 'string',
            'password'  => 'string',
            'radio'     => 'string',
            'color'     => 'string',
            'date'      => 'string',
            'time'      => 'string',
            'datetime'  => 'string',
            'number'    => 'number',
            'checkbox'  => 'boolean',
        ];

        if (isset($this->field)) {
            $fieldType    = $this->field->getVariation() ?? 'text';
            $requiredType = $map[$fieldType] ?? 'string';
        }

        return $this->sanitizeValue($value, $requiredType);
    }

    /**
     * Recursively sanitizes each value in an array using the generic sanitize helper.
     *
     * @param array $items The array of values to sanitize.
     *
     * @return array The sanitized array.
     */
    protected function sanitizeArray(array $items): array {
        // Repeatable array of objects
        if ($this->itemType === 'object') {
            $existing = $this->cachedExisting ?? $this->getValue();
            $this->cachedExisting = $existing;

            $items = is_array($items) ? $items : [];

            $sanitizedItems = [];

            foreach ($items as $_index => $row) {
                $row          = is_array($row) ? $row : [];
                $sanitizedRow = [];

                foreach ($this->subItems as $child) {
                    $childName  = $child->getName();
                    $inputValue = data_get($row, $childName);

                    if (($child->getDataType()) === 'boolean') {
                        $exists = data_get($row, $childName, '__missing__') !== '__missing__';

                        $sanitized = $exists
                            ? filter_var($inputValue, FILTER_VALIDATE_BOOLEAN)
                            : null;
                    } else {
                        $sanitized = $child->sanitize($inputValue);
                    }

                    data_set($sanitizedRow, $childName, $sanitized);
                }

                $sanitizedItems[] = $sanitizedRow;
            }

            $this->cachedExisting = null;

            return $sanitizedItems;
        }

        // Simple array
        foreach ($items as $key => $value) {
            $items[$key] = is_array($value)
                ? $this->sanitizeArray($value)
                : $this->sanitizeValue($value, $this->itemType);
        }

        $this->cachedExisting = null;

        return $items;
    }

    /**
     * Sanitizes the value of an object setting by sanitizing each of its 
     * sub-settings according to their types and the setting's schema.
     *
     * @param mixed $input The input value to sanitize, expected to be an array or object.
     *
     * @return array The sanitized value as an associative array.
     */
    protected function sanitizeObject(mixed $input): array {
        $input = is_array($input) ? $input : [];

        $existing = $this->cachedExisting ?? $this->getValue();
        $this->cachedExisting = $existing;

        $merged = array_replace_recursive($existing ?? [], $input);

        $sanitized = [];

        foreach ($this->subItems as $child) {
            $childName = $child->getName();

            $inputValue        = data_get($input, $childName);
            $mergedValue       = data_get($merged, $childName);
            $nameExistsInInput = data_get($input, $childName, '__missing__') !== '__missing__';
            $childType         = $child->getDataType();

            if ($childType === 'boolean') {
                $exists = $nameExistsInInput;

                $value = $exists
                    ? filter_var($inputValue, FILTER_VALIDATE_BOOLEAN)
                    : $mergedValue;
            } 
            
            else if ($childType === 'array' || $childType === 'object') {
                // For structured children, prefer submitted input to avoid retaining removed indices.
                $value = $child->sanitize($nameExistsInInput ? $inputValue : $mergedValue);
            } 
            
            else {
                // Important: always sanitize merged value so defaults persist
                $value = $child->sanitize($mergedValue);
            }

            data_set($sanitized, $childName, $value);
        }

        $this->cachedExisting = null;

        return $sanitized;
    }

    /**
     * Sanitizes a value based on the required type. 
     *
     * @param mixed  $value
     * @param string $requiredType
     * 
     * @return mixed The sanitized value
     */
    protected function sanitizeValue(mixed $value, string $requiredType): mixed {
        $type = gettype($value);

        switch ($requiredType) {
            case 'string':
            case 'text':
            case 'tel':
            case 'password':
            case 'date':
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
            case 'checkbox':
                $value = (bool) $value;
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
    protected function sanitizeTextValue(mixed $value, string $type, string $requiredType): string {
        if ($type === 'string') {
            if (in_array($requiredType, ['text', 'select'])) {
                $value = sanitize_text_field($value);
            } 
            
            else if ($requiredType === 'textarea') {
                $value = sanitize_textarea_field($value);
            }
        } 
        
        else {
            return is_scalar($value) ? (string) $value : '';
        }

        return $value;
    }
}