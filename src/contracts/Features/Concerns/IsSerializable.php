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
     * Serializes the feature instance into the specified format, which can be 'array', 'json', or 'php' (for PHP's serialize() function).
     *
     * @param string $format The format to serialize the feature instance into.
     * @param string ...$flags Optional flags to pass to the serialization function, depending on the chosen format.
     *
     * @return array|string The serialized representation of the feature instance.
     */
    final public function toSerialized(string $format = 'array', string ...$flags): array|string {
        return match ($format) {
            'array' => $this->toArray(),
            'json'  => $this->toJson(...$flags),
            'php'   => serialize($this->toArray()),
            default => throw new \InvalidArgumentException("Unsupported serialization format: {$format}. Supported formats are 'array', 'json', and 'php'."),
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
     * Returns an array representation of the feature instance, including its type, identifier, and specified serializable properties.
     * Circular references are guarded to prevent infinite recursion.
     *
     * @return array
     */
    final public function toArray(): array {
        static $stack = [];

        $objectId = spl_object_id($this);

        if (isset($stack[$objectId])) {
            return [
                'type'       => static::class,
                'identifier' => $this->getIdentifier(),
                'properties' => ['__circular_reference' => true],
            ];
        }

        $stack[$objectId] = true;

        try {
            $properties = [];

            foreach ($this->serializableProperties as $property) {
                $properties[$property] = $this->resolveSerializableProperty($property);
            }

            return [
                'type'       => static::class,
                'identifier' => $this->getIdentifier(),
                'properties' => $properties,
            ];
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
    private function resolveSerializableProperty(string $property): mixed {
        $getter = 'get' . ucfirst($property);
        $isser  = 'is' . ucfirst($property);

        if (method_exists($this, $getter)) {
            return $this->serializeValue($this->{$getter}());
        }

        if (method_exists($this, $isser)) {
            return $this->serializeValue($this->{$isser}());
        }

        if (property_exists($this, $property)) {
            return $this->serializeValue($this->{$property});
        }

        return null;
    }

    /**
     * Serialises nested values recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    private function serializeValue(mixed $value): mixed {
        if ($value instanceof Serializable) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->serializeValue($item), $value);
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
}