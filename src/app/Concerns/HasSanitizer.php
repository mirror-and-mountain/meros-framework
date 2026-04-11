<?php 

namespace MM\Meros\App\Concerns;

use Closure;

use MM\Meros\App\Support\PathResolver;
use MM\Meros\App\Support\Admin\Helpers;

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
     * @param  callable|Closure $callback A callable or method reference for sanitizing the setting's value.
     *
     * @return self
     */
    public function sanitizer(callable|Closure $callback): self {
        $this->args['sanitize_callback'] = $this->convertToClosure($callback);

        $this->setReady();
        return $this;
    }

    /**
     * Default sanitizer for settings values.
     *
     * @param  mixed   $value
     *
     * @return mixed
     */
    final public function sanitizeValue(mixed $value): mixed {
        $type = $this->args['type'] ?? 'string';

        if ($type === 'array') {
            return $this->sanitizeArray(is_array($value) ? $value : []);
        }

        if ($type === 'object') {
            return $this->sanitizeObject($value);
        }

        $requiredType = $type;

        // Only allow field type override for scalar values
        if (isset($this->field) && !in_array($type, ['array', 'object'])) {
            $requiredType = $this->field->type;
        }

        return Helpers::sanitize($value, $requiredType);
    }

    /**
     * Recursively sanitizes each value in an array using the generic sanitize helper.
     *
     * @param  array $items The array of values to sanitize.
     *
     * @return array The sanitized array.
     */
    protected function sanitizeArray(array $items): array {
        $itemType = $this->args['item_type'] ?? 'string';

        // Repeatable array of objects
        if ($itemType === 'object') {
            $existing = $this->cachedExisting ?? $this->getValue();
            $this->cachedExisting = $existing;

            $resolver = $this->resolver();
            $items = is_array($items) ? $items : [];

            $sanitizedItems = [];

            foreach ($items as $_index => $row) {
                $row = is_array($row) ? $row : [];

                $sanitizedRow = [];

                foreach ($this->subItems as $child) {
                    // strip root and array marker (e.g. my_setting.*.foo -> foo)
                    $relativePath = $resolver->stripArrayRoot($child->path);

                    $inputValue = data_get($row, $relativePath);

                    if (($child->args['type'] ?? null) === 'boolean') {
                        $exists = data_get($row, $relativePath, '__missing__') !== '__missing__';

                        $sanitized = $exists
                            ? filter_var($inputValue, FILTER_VALIDATE_BOOLEAN)
                            : null;
                    } else {
                        $sanitized = $child->sanitizeValue($inputValue);
                    }

                    data_set($sanitizedRow, $relativePath, $sanitized);
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
                : Helpers::sanitize($value, $itemType);
        }

        $this->cachedExisting = null;

        return $items;
    }

    /**
     * Sanitizes the value of an object setting by sanitizing each of its 
     * sub-settings according to their types and the setting's schema.
     *
     * @param  mixed $input The input value to sanitize, expected to be an array or object.
     *
     * @return array The sanitized value as an associative array.
     */
    protected function sanitizeObject(mixed $input): array {
        $input = is_array($input) ? $input : [];

        $existing = $this->cachedExisting ?? $this->getValue();
        $this->cachedExisting = $existing;

        $merged = array_replace_recursive($existing ?? [], $input);

        $resolver = $this->resolver();
        $sanitized = [];

        foreach ($this->subItems as $child) {
            $relativePath = $resolver->stripRoot($child->path);

            $inputValue  = data_get($input, $relativePath);
            $mergedValue = data_get($merged, $relativePath);

            if (($child->args['type'] ?? null) === 'boolean') {
                $exists = data_get($input, $relativePath, '__missing__') !== '__missing__';

                $value = $exists
                    ? filter_var($inputValue, FILTER_VALIDATE_BOOLEAN)
                    : $mergedValue;
            } else {
                // Important: always sanitize merged value so defaults persist
                $value = $child->sanitizeValue($mergedValue);
            }

            data_set($sanitized, $relativePath, $value);
        }

        $this->cachedExisting = null;

        return $sanitized;
    }

    /**
     * Helper to reuse a single PathResolver instance.
     *
     * @return PathResolver
     */
    protected function resolver(): PathResolver {
        return new PathResolver($this->optionName);
    }
}