<?php 

namespace MM\Meros\App\Support\Fields;

use MM\Meros\App\FeatureProvider;

class Resolver {
   /**
    * Factory method to create a Field instance based on the provided type and feature provider.
    *
    * @param string          $type            The type of field to create (e.g., 'text', 'select').
    * @param FeatureProvider $featureProvider The source feature provider for the field.
    *
    * @return Field The instantiated Field object corresponding to the specified type.
    */
    public static function make(
        string          $type,
        array           $config = [],
        FeatureProvider $featureProvider
    ): Field {
        return self::resolve($type, $config, $featureProvider);
    }

    /**
     * Resolves a field type to its corresponding class instance.
     *
     * @param string          $type
     * @param FeatureProvider $featureProvider
     *
     * @return Field
     * @throws \InvalidArgumentException If the provided field type is not recognised.
     */
    protected static function resolve(
        string          $type,
        array           $config,
        FeatureProvider $featureProvider
    ): Field {
        $map = [
            'text'        => Input::class,
            'email'       => Input::class,
            'number'      => Input::class,
            'password'    => Input::class,
            'url'         => Input::class,
            'tel'         => Input::class,
            'checkbox'    => Input::class,
            'select'      => Select::class,
            'radio'       => Radio::class,
            'checkboxes'  => Checkboxes::class,
            'textarea'    => Textarea::class,
            'repeater'    => RepeaterTable::class,
        ];

        $class = $map[$type] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("Field type '{$type}' not found.");
        }

        if ($class === Input::class) {
            $instance = new Input(
                source: $featureProvider
            );

            return $instance->variation($type)->configure($config);
        }

        return new $class(
            source: $featureProvider
        )->configure($config);
    }
}