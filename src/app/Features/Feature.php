<?php 

namespace MM\Meros\App\Features;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Contracts\FeatureDefinition;
use MM\Meros\App\Facades\Registry;

abstract class Feature implements FeatureDefinition {
    /**
     * Unique identifier for the feature
     *
     * @var string
     */
    public string $handle;

    /**
     * Indicates that the feature's configuration is valid and
     * that the feature is ready to be hooked into WordPress.
     *
     * @var boolean
     */
    public bool $ready = false;

    /**
     * Indicates that the feature has been loaded.
     *
     * @var boolean
     */
    public bool $loaded = false;
    
    /**
     * Error message if the configuration is invalid.
     *
     * @var string
     */
    public string $error = '';

    /**
    * Configuration schema for the feature.
    *
    * @var array
    */
    protected array $configSchema = [];

    /**
     * Method to create an instance of the feature from a config array.
     *
     * @param  array $config Configuration array for the feature.
     * 
     * @return self An instance of the feature.
     */
    abstract public function make(array $config): self;

    /**
     * Method to load the feature by hooking it into WordPress.
     *
     * @return void
     */
    abstract public function load(): void;

    /**
     * Set the configuration schema for the feature.
     *
     * @return void
     */
    abstract protected function setSchema(): void;
    
    /**
     * Sanitize and validate the configuration array against the schema.
     *
     * @param array        $config The configuration array to sanitize and validate.
     * @param array        $schema Optional schema to use for validation. If not provided, the feature's configSchema will be used.
     * 
     * @return array|false The sanitized configuration array if valid, or false if invalid.
     */
    protected function sanitizeConfig(array $config, array $schema = []): array|false {
        $schema = empty($schema) ? $this->configSchema : $schema;
        foreach ($schema as $key => $rules) {
            $validator     = $rules['validator'] ?? null;
            $type          = $rules['type'] ?? 'mixed';
            $required      = $rules['required'] ?? false;
            $default       = $rules['default'] ?? null;
            $allowedValues = $rules['allowed_values'] ?? [];
            $checkPath     = $rules['check_path'] ?? false;
            $pathType      = $rules['path_type'] ?? 'file';

            // If a custom validator is provided, use it to validate and sanitize the value
            if (is_callable($validator)) {
                $config[$key] = call_user_func($validator, $config[$key] ?? $default);
                continue;
            }

            // Check if the field is required and missing
            if ($required && !isset($config[$key])) {
                $this->error = "The '{$key}' field is required.";
                return false;
            }

            // If the field is not required and missing, set it to the default value
            $value = $config[$key] ?? null;

            // If the value is null and the type allows null, continue without error
            if (is_null($value) && strpos($type, 'null') !== false) {
                $config[$key] = null;
                continue;
            }

            // If the value is null and a default value is provided, set it to the default value
            if (is_null($value) && $default !== null) {
                $config[$key] = $default;
                continue;
            }

            // Validate the type of the value
            if (!$this->isValidType($value, $type)) {
                $this->error = "The '{$key}' field must be of type {$type}.";
                return false;
            }

            // If there are allowed values defined for the field, check if the value is in the allowed values
            if (!empty($allowedValues) && !in_array($value, $allowedValues)) {
                $this->error = "The '{$key}' field must be one of: " . implode(', ', $allowedValues) . ".";
                return false;
            }

            // If the field is a path, check if the path exists and is of the correct type (file or directory)
            if ($key === 'path' && $checkPath && !$this->pathIsValid($value, $pathType)) {
                return false;
            }

            // If the field is a handle, check if it is unique across all features of the same type
            if ($key === 'handle' && !$this->handleIsUnique($value)) {
                return false;
            }

            $config[$key] = $value;
        }

        return $config;
    }

    /**
     * Checks that the given value is of an accepted type.
     *
     * @param  mixed   $value
     * @param  string  $type
     *
     * @return boolean
     */
    private function isValidType(mixed $value, string $type): bool {
        $expectedTypes = explode('|', $type);

        foreach ($expectedTypes as $expectedType) {
            return match ($expectedType) {
                'callable' => is_callable($value),
                'closure'  => $value instanceof Closure,
                'array'    => is_array($value),
                'mixed'    => true,
                'integer'  => is_int($value),
                'boolean'  => is_bool($value),
                'string'   => is_string($value),
                'object'   => is_object($value),
                'numeric'  => is_numeric($value),
                default    => gettype($value) === $expectedType,
            };
        }

        return false;
    }

    /**
     * Validates that a given path exists and is of the correct type (file or directory).
     *
     * @param  string $value The path to validate.
     * @param  string $pathType The type of path to validate ('file' or 'directory').
     * 
     * @return bool   True if the path is valid, false otherwise.
     */
    private function pathIsValid(string $value, string $pathType): bool {
        if ($pathType === 'file' && (!File::exists($value) || !File::isFile($value))) {
            $this->error = "The file path specified does not exist or is not a file.";
            return false;
        }

        if ($pathType === 'directory' && (!File::exists($value) || !File::isDirectory($value))) {
            $this->error = "The directory path specified does not exist or is not a directory.";
            return false;
        }

        return true;
    }

    /**
     * Verifies that the given handle is unique across all features of the same type.
     *
     * @param  string $handle The handle to verify.
     * 
     * @return bool   True if the handle is unique, false otherwise.
     */
    private function handleIsUnique(string $handle): bool {
        $type = class_basename($this);
    
        $existingHandles = Registry::get(strtolower($type) . 's')
            ->pluck('handle')
            ->toArray();

        if (in_array($handle, $existingHandles)) {
            $this->error = "The handle '{$handle}' is already in use for another " . $type . ". Handles must be unique.";
            return false;
        }

        return true;
    }

    /**
     * Converts a callable to a Closure instance.
     *
     * @param  callable|Closure $callback The callback to convert.
     * 
     * @return Closure|false The converted Closure instance, or false if the input is not callable.
     */
    protected function convertToClosure(callable|Closure $callback): Closure|false {
        if ($callback instanceof Closure) {
            return $callback;
        } elseif (is_callable($callback)) {
            return Closure::fromCallable($callback);
        } else {
            return false;
        }
    }
}