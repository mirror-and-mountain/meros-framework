<?php

namespace MM\Meros\Contracts\Features;

interface Serializable {
    /**
     * Serializes the feature instance into the specified format, which can be 'array', 'json', or 'php' (for PHP's serialize() function). 
     * The method returns the serialized representation of the feature instance.
     * 
     * @param string $format The format to serialize the feature instance into. May be 'array', 'json', or 'php' (for PHP's serialize() function). Defaults to 'array'.
     * @param string ...$flags Optional flags to pass to the serialization function, depending on the chosen format.
     *
     * @return array|string The serialized representation of the feature instance.
     */
    public function serialize(string $format = 'array', string ...$flags): array|string;

    /**
     * Returns an array representation of the feature instance, including its type, identifier, and specified serializable properties.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Returns a JSON representation of the feature instance, including its type, identifier, and specified serializable properties.
     * 
     * @param string ...$flags Optional flags to pass to json_encode() for customizing the JSON output.
     *
     * @return string
     */
    public function toJson(string ...$flags): string;
}