<?php 

namespace MM\Meros\App\Support\Fields;

use MM\Meros\App\Contracts\FieldRegistrar;
use MM\Meros\App\FeatureProvider;

class FieldResolver {

    /**
     * Undocumented function
     *
     * @param string          $type
     * @param FieldRegistrar  $registrar
     * @param FeatureProvider $featureProvider
     *
     * @return Field
     */
    public static function resolve(
        string          $type, 
        FieldRegistrar  $registrar, 
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
                source    : $featureProvider,
                registrar : $registrar
            );

            return $instance->variation($type);
        }

        return new $class(
            source    : $featureProvider,
            registrar : $registrar
        );
    }
}