<?php

namespace MM\Meros\Support;

class Sanitizer {
    /**
     * The raw value to be sanitized.
     *
     * @var mixed
     */
    private mixed $rawValue;

    /**
     * A schema that maps keys to their expected data types for sanitization.
     *
     * @var array
     */
    private array $schema;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @param mixed $input  The value to be sanitized.
     * @param array $schema The schema that maps keys to their expected data types for sanitization.
     * 
     */
    private function __construct(mixed $input, array $schema = []) {
        $this->rawValue = $input;
        $this->schema   = $schema;
    }

    /**
     * Creates a new instance and sanitizes the given value.
     *
     * @param mixed $input   The value to be sanitized.
     * @param array $schema  The schema that maps keys to their expected data types for sanitization.
     * 
     * @return mixed The sanitized value.
     */
    public static function sanitize(mixed $input, array $schema = []): mixed {
        $instance = new self($input, $schema);
        return $instance->processValue();
    }

    /**
     * Processes the raw value based on its type and returns the sanitized value.
     *
     * @return mixed The sanitized value.
     */
    private function processValue(): mixed {
        $schema = $this->schema;

        if (isset($schema['schema']) && is_array($schema['schema'])) {
            $schema = $schema['schema'];
        }

        return $this->sanitizeBySchema($this->rawValue, is_array($schema) ? $schema : []);
    }

    // =========================================================================
    // Sanitization Methods
    // =========================================================================

    /**
     * Sanitizes the given value based on the provided schema and returns the sanitized value.
     *
     * @param mixed $value   The value to be sanitized.
     * @param array $schema  The schema that maps keys to their expected data types for sanitization.
     *
     * @return mixed The sanitized value.
     */
    private function sanitizeBySchema(mixed $value, array $schema = []): mixed {
        if ($value === null && array_key_exists('default', $schema)) {
            $value = $schema['default'];
        }

        $type = $schema['type'] ?? $this->inferType($value);

        return match ($type) {
            'object'  => $this->sanitizeObject(is_array($value) ? $value : [], $schema),
            'array'   => $this->sanitizeArrayBySchema(is_array($value) ? $value : [], $schema),
            'string'  => $this->sanitizeString((string) ($value ?? '')),
            'integer' => $this->sanitizeInteger($value),
            'number'  => $this->sanitizeNumber($value),
            'boolean' => $this->sanitizeBoolean($value),
            default   => $this->sanitizeUnknown($value),
        };
    }

    // =========================================================================
    // Object Sanitization
    // =========================================================================

    /**
     * Sanitizes an associative array (object) based on the provided schema and returns the sanitized array.
     *
     * @param array $input   The associative array to be sanitized.
     * @param array $schema  The schema that maps keys to their expected data types for sanitization.
     *
     * @return array The sanitized associative array.
     */
    private function sanitizeObject(array $input, array $schema): array {
        $output = [];
        $properties = [];

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $properties = $schema['properties'];
        }

        foreach ($input as $key => $value) {
            $sanitizedKey = $this->sanitizeArrayKey($key);
            $propertySchema = $properties[$sanitizedKey] ?? null;

            if (is_array($propertySchema)) {
                $output[$sanitizedKey] = $this->sanitizeBySchema($value, $propertySchema);
                continue;
            }

            $output[$sanitizedKey] = $this->sanitizeUnknown($value);
        }

        foreach ($properties as $propertyKey => $propertySchema) {
            if (!is_array($propertySchema)) {
                continue;
            }

            if (array_key_exists($propertyKey, $output)) {
                continue;
            }

            if (array_key_exists('default', $propertySchema)) {
                $output[$propertyKey] = $this->sanitizeBySchema($propertySchema['default'], $propertySchema);
            }
        }

        return $output;
    }

    // =========================================================================
    // Array Sanitization
    // =========================================================================

    /**
     * Sanitizes an array based on the provided schema and returns the sanitized array.
     *
     * @param array $input   The array to be sanitized.
     * @param array $schema  The schema that maps keys to their expected data types for sanitization.
     *
     * @return array The sanitized array.
     */
    private function sanitizeArrayBySchema(array $input, array $schema): array {
        $itemSchema = [];

        if (isset($schema['items']) && is_array($schema['items'])) {
            $itemSchema = $schema['items'];
        }

        if (empty($itemSchema)) {
            return $this->sanitizeArray($input);
        }

        $output = [];

        foreach ($input as $key => $value) {
            $sanitizedKey = is_int($key) ? $key : $this->sanitizeArrayKey($key);
            $output[$sanitizedKey] = $this->sanitizeBySchema($value, $itemSchema);
        }

        return $output;
    }

    /**
     * Sanitizes a simple array (without schema) and returns the sanitized array.
     *
     * @param array $input The simple array to be sanitized.
     *
     * @return array The sanitized simple array.
     */
    private function sanitizeArray(array $input): array {
        $output = [];

        foreach ($input as $key => $value) {
            $sanitizedKey = is_int($key) ? $key : $this->sanitizeArrayKey($key);

            if (is_array($value)) {
                $output[$sanitizedKey] = $this->sanitizeArray($value);
                continue;
            }

            $output[$sanitizedKey] = $this->sanitizeScalar($value);
        }

        return $output;
    }

    /**
     * Sanitizes an array key to ensure it is a valid string or integer.
     *
     * @param mixed $key The array key to be sanitized.
     *
     * @return string|int The sanitized array key.
     */
    private function sanitizeArrayKey(mixed $key): string|int {
        if (is_int($key)) {
            return $key;
        }

        $key = (string) $key;

        if (function_exists('sanitize_key')) {
            return sanitize_key($key);
        }

        return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key));
    }

    // =========================================================================
    // Scalar Sanitization
    // =========================================================================

    /**
     * Sanitizes a scalar value based on its type and returns the sanitized value.
     *
     * @param mixed $input The scalar value to be sanitized.
     *
     * @return mixed The sanitized scalar value.
     */
    private function sanitizeScalar(mixed $input): mixed {
        return match (gettype($input)) {
            'string'  => $this->sanitizeString($input),
            'integer' => $this->sanitizeInteger($input),
            'double'  => $this->sanitizeNumber($input),
            'boolean' => $this->sanitizeBoolean($input),
            'NULL'    => null,
            default   => $this->sanitizeString((string) $input),
        };
    }

    /**
     * Sanitizes an integer value and returns the sanitized integer.
     *
     * @param mixed $input
     *
     * @return integer
     */
    private function sanitizeInteger(mixed $input): int {
        if (is_int($input)) {
            return $input;
        }

        if (is_bool($input)) {
            return $input ? 1 : 0;
        }

        if (is_numeric($input)) {
            return (int) $input;
        }

        return 0;
    }

    /**
     * Sanitizes a number (float) value and returns the sanitized float.
     *
     * @param mixed $input
     *
     * @return float
     */
    private function sanitizeNumber(mixed $input): float {
        if (is_float($input) || is_int($input)) {
            return (float) $input;
        }

        if (is_numeric($input)) {
            return (float) $input;
        }

        return 0.0;
    }

    /**
     * Sanitizes a boolean value and returns the sanitized boolean.
     *
     * @param mixed $input
     *
     * @return bool
     */
    private function sanitizeBoolean(mixed $input): bool {
        if (is_bool($input)) {
            return $input;
        }

        if (is_int($input) || is_float($input)) {
            return $input !== 0;
        }

        $validated = filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $validated ?? false;
    }

    /**
     * Sanitizes a string value and returns the sanitized string.
     *
     * @param string $input
     *
     * @return string
     */
    private function sanitizeString(string $input): string {
        if (str_contains($input, "\n") || str_contains($input, "\r")) {
            if (function_exists('sanitize_textarea_field')) {
                return sanitize_textarea_field($input);
            }

            return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $input) ?? '');
        }

        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($input);
        }

        return trim(strip_tags($input));
    }

    /**
     * Sanitizes an unknown value type and returns the sanitized value.
     *
     * @param mixed $input The value to be sanitized.
     *
     * @return mixed The sanitized value.
     */
    private function sanitizeUnknown(mixed $input): mixed {
        if (is_array($input)) {
            return $this->sanitizeArray($input);
        }

        return $this->sanitizeScalar($input);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Infers the data type of the given value.
     *
     * @param mixed $value The value whose data type is to be inferred.
     *
     * @return string The inferred data type as a string.
     */
    private function inferType(mixed $value): string {
        if (is_array($value)) {
            return $this->isAssociativeArray($value) ? 'object' : 'array';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'number';
        }

        return 'string';
    }

    /**
     * Determines if the given array is associative.
     *
     * @param array $value The array to be checked.
     *
     * @return bool True if the array is associative, false otherwise.
     */
    private function isAssociativeArray(array $value): bool {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}