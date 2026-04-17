<?php 

namespace MM\Meros\App\Support\Fields;

/**
 * Flexible field class used only to resolve a concrete field type.
 */
class Maker extends Field {
    /**
     * Resolves an instantiates a field type. 
     * The Resolver::make method will throw an exception if the given $type is invalid.
     *
     * @param string $type
     * @param array  $config
     *
     * @return static
     */
    public function make(string $type, array $config = []): Field {
        return Resolver::make(
            type: $type,
            config: $config,
            featureProvider: $this->source
        );
    }

    /**
     * Retrieves the name of the Blade component responsible for rendering this field type.
     * Note: This method returns an empty string as Maker should not be rendered directly. 
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return '';
    }
}