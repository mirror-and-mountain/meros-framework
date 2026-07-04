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
            'rich_text' => 'rich_text',
            'rich-text' => 'rich_text',
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
            $fieldType = null;

            if (method_exists($this->field, 'getVariation')) {
                $fieldType = $this->field->getVariation();
            }

            if (empty($fieldType) && method_exists($this->field, 'getType')) {
                $fieldType = $this->field->getType();
            }

            $fieldType = strtolower(str_replace('-', '_', (string) ($fieldType ?? 'text')));
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

            // Empty-state marker used by repeater settings fields to ensure the
            // option key is submitted when all rows are removed.
            if (array_key_exists('__empty', $items)) {
                unset($items['__empty']);
            }

            $sanitizedItems = [];

            foreach ($items as $_index => $row) {
                if (!is_array($row)) {
                    continue;
                }

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

        // Empty-state marker used by array.scalar controls (e.g. multi_select)
        // to ensure an explicit empty array can be submitted.
        if (array_key_exists('__empty', $items)) {
            unset($items['__empty']);
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
                $fallbackInput = $this->extractStructuredFallbackInput($input, $child);

                $value = $child->sanitize(
                    $nameExistsInInput
                        ? $inputValue
                        : ($fallbackInput ?? $mergedValue)
                );
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

            case 'rich_text':
            case 'rich-text':
                $value = $this->sanitizeRichTextValue($value);
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

            else if ($requiredType === 'rich_text') {
                $value = $this->sanitizeRichTextValue($value);
            }
        } 
        
        else {
            return is_scalar($value) ? (string) $value : '';
        }

        return $value;
    }

    /**
     * Sanitizes rich text HTML using WordPress' post-content allowlist.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function sanitizeRichTextValue(mixed $value): string {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;

        if (function_exists('wp_kses_post')) {
            return wp_kses_post($value);
        }

        return strip_tags($value);
    }

    /**
     * Attempts to recover malformed structured input for nested items.
     *
     * Supports payloads where repeater rows are submitted at the current level
     * as numeric keys (e.g. option[0][sub_field]) instead of under child key.
     *
     * @param array $input
     * @param mixed $child
     *
     * @return array|null
     */
    protected function extractStructuredFallbackInput(array $input, mixed $child): ?array {
        if (!is_object($child) || !method_exists($child, 'getDataType')) {
            return null;
        }

        $childType = $child->getDataType();

        if ($childType !== 'array' || !method_exists($child, 'getItemDataType') || $child->getItemDataType() !== 'object') {
            return null;
        }

        $candidateRows = [];

        foreach ($input as $key => $row) {
            if ((is_int($key) || ctype_digit((string) $key)) && is_array($row)) {
                $candidateRows[] = $row;
            }
        }

        if (empty($candidateRows)) {
            return null;
        }

        $expectedKeys = [];

        if (method_exists($child, 'getSubItems')) {
            foreach ($child->getSubItems() as $subItem) {
                if (is_object($subItem) && method_exists($subItem, 'getName')) {
                    $name = $subItem->getName();

                    if (is_string($name) && $name !== '') {
                        $expectedKeys[] = $name;
                    }
                }
            }
        }

        if (empty($expectedKeys)) {
            return array_values($candidateRows);
        }

        $filteredRows = [];

        foreach ($candidateRows as $row) {
            foreach ($expectedKeys as $expectedKey) {
                if (array_key_exists($expectedKey, $row)) {
                    $filteredRows[] = $row;
                    break;
                }
            }
        }

        if (empty($filteredRows)) {
            return null;
        }

        return array_values($filteredRows);
    }
}