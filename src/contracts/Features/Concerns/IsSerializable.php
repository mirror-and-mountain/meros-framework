<?php

namespace MM\Meros\Contracts\Features\Concerns;

use MM\Meros\Contracts\Features\Serializable;

trait IsSerializable {
    /**
     * Returns the unique identifier for the feature instance.
     *
     * @param string $format The format in which to return the identifier. Defaults to 'default'.
     *
     * @return string
     */
    abstract public function getIdentifier(string $format = 'default'): string;

    /**
     * An array of property names that should be serialised when the object is converted to an array or JSON.
     *
     * @var array
     */
    protected array $serializableProperties = [];

    /**
     * Properties generated for runtime use and excluded from storage serialization.
     *
     * @var array
     */
    protected array $nonPersistableProperties = [];

    /**
     * Specifies which properties of the object should be serialised.
     *
     * @param array $properties An array of property names to be serialised.
     * @param bool  $merge      Whether to merge the specified properties with the existing serialisable properties (true) or replace them (false). Defaults to true.
     *
     * @return void
     */
    protected function setSerializableProperties(array $properties, bool $merge = true): void {
        if ($merge) {
            $this->serializableProperties = array_unique(array_merge($this->serializableProperties, $properties));
        } else {
            $this->serializableProperties = $properties;
        }
    }

    /**
     * Specifies properties to omit from storage serialization.
     *
     * @param array $properties
     * @param bool  $merge
     *
     * @return void
     */
    protected function setNonPersistableProperties(array $properties, bool $merge = true): void {
        if ($merge) {
            $this->nonPersistableProperties = array_unique(array_merge($this->nonPersistableProperties, $properties));
        } else {
            $this->nonPersistableProperties = $properties;
        }
    }

    /**
    * Serializes the feature instance into the specified format. The 'storage' format returns an array without runtime-only properties.
     *
    * @param string $format The format to serialize the feature instance into: 'array', 'json', 'php', or 'storage'.
     * @param string ...$flags Optional flags to pass to the serialization function, depending on the chosen format.
     *
     * @return array|string The serialized representation of the feature instance.
     */
    final public function serialize(string $format = 'array', string ...$flags): array|string {
        return match ($format) {
            'array' => $this->toArray(),
            'json'  => $this->toJson(...$flags),
            'php'   => serialize($this->toArray()),
            'storage' => $this->toStorageArray(),
            default => throw new \InvalidArgumentException("Unsupported serialization format: {$format}. Supported formats are 'array', 'json', 'php', and 'storage'."),
        };
    }

    /**
     * Returns a JSON representation of the feature instance, including its type, identifier, and specified serializable properties.
     *
     * @param string ...$flags Optional flags to pass to json_encode() for customizing the JSON output.
     *
     * @return string
     */
    final public function toJson(string ...$flags): string {
        return json_encode($this->toArray(), ...$flags);
    }

    /**
    * Returns an array representation of the feature instance's specified serializable properties.
     * Circular references are guarded to prevent infinite recursion.
     *
     * @return array
     */
    final public function toArray(): array {
        return $this->resolveArray();
    }

    /**
     * Returns a recursive array representation suitable for persistence.
     *
     * @return array
     */
    final public function toStorageArray(): array {
        return $this->resolveArray(true);
    }

    private function resolveArray(bool $forStorage = false): array {
        static $stack = [];

        $objectId = spl_object_id($this);

        if (isset($stack[$objectId])) {
            return ['__circular_reference' => true];
        }

        $stack[$objectId] = true;

        try {
            $properties = [];

            foreach ($this->serializableProperties as $property) {
                if ($forStorage && in_array($property, $this->nonPersistableProperties, true)) {
                    continue;
                }

                $properties[$property] = $this->resolveSerializableProperty($property, $forStorage);
            }

            $properties = $this->filterSerializedProperties($properties);

            return $forStorage
                ? $this->filterStorageProperties($properties)
                : $properties;
        } finally {
            unset($stack[$objectId]);
        }
    }

    /**
     * Resolves a serializable property value using getter/isser/property lookup.
     *
     * @param string $property
     * @return mixed
     */
    private function resolveSerializableProperty(string $property, bool $forStorage = false): mixed {
        $getter = 'get' . ucfirst($property);
        $isser  = 'is' . ucfirst($property);
        $hasser = 'has' . ucfirst($property);

        if (method_exists($this, $getter)) {
            return $this->serializeValue($this->{$getter}(), $forStorage);
        }

        if (method_exists($this, $isser)) {
            return $this->serializeValue($this->{$isser}(), $forStorage);
        }

        if (method_exists($this, $hasser)) {
            return $this->serializeValue($this->{$hasser}(), $forStorage);
        }

        if (property_exists($this, $property)) {
            return $this->serializeValue($this->{$property}, $forStorage);
        }

        return null;
    }

    /**
     * Serialises nested values recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    private function serializeValue(mixed $value, bool $forStorage = false): mixed {
        if ($value instanceof Serializable) {
            return $forStorage && method_exists($value, 'toStorageArray')
                ? $value->toStorageArray()
                : $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->serializeValue($item, $forStorage), $value);
        }

        return $value;
    }

    /**
     * Filters serialized properties after they have been resolved.
     *
     * @param array $properties
     *
     * @return array
     */
    protected function filterSerializedProperties(array $properties): array {
        return $properties;
    }

    /**
     * Filters runtime-only data from a storage representation.
     *
     * @param array $properties
     *
     * @return array
     */
    protected function filterStorageProperties(array $properties): array {
        return $properties;
    }
}