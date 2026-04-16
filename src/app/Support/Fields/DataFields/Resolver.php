<?php 

namespace MM\Meros\App\Support\Fields\DataFields;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Fields\DataField;
use MM\Meros\App\Contracts\DataFieldRegistrar;

class Resolver {

    /**
     * Undocumented function
     *
     * @param string              $type
     * @param DataFieldRegistrar  $registrar
     * @param FeatureProvider     $featureProvider
     *
     * @return DataField
     */
    public static function resolve(
        string              $type, 
        DataFieldRegistrar  $registrar, 
        FeatureProvider     $featureProvider
    ): DataField {
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