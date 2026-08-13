<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Register;

// Coming soon: Discoverable assets will be supported in the future, but for now, this register is only for assets that are explicitly registered/made.
use MM\Meros\Contracts\Registers\AllFeatureRegistrationMethods;
use MM\Meros\Contracts\Registers\Concerns\ProvidesAllFeatureRegistrationMethods;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Contracts\Features\Assets\Script;
use MM\Meros\Contracts\Features\Assets\Style;

use MM\Meros\Contracts\Features\Assets\AdminScript;
use MM\Meros\Contracts\Features\Assets\AdminStyle;

use MM\Meros\Contracts\Features\Assets\EditorScript;
use MM\Meros\Contracts\Features\Assets\EditorStyle;

use MM\Meros\Facades\Assets\Scripts;
use MM\Meros\Facades\Assets\Styles;

use MM\Meros\Facades\Assets\AdminScripts;
use MM\Meros\Facades\Assets\AdminStyles;

use MM\Meros\Facades\Assets\EditorScripts;
use MM\Meros\Facades\Assets\EditorStyles;

abstract class Assets extends Register implements RegistrarMaker {
    /**
     * The class name of the asset type that this register manages.
     * Should be set in implementing classes.
     *
     * @var string
     */
    protected string $assetClass = '';

    use IsRegistrarMaker;

    final protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->definition($this->assetClass);
    }

    /**
     * Retrieves the appropriate register for the given asset feature class.
     *
     * @param string $featureClass
     *
     * @return Assets
     */
    final public static function resolveDiscovererRegister(string $featureClass): Assets {
        return match ($featureClass) {
            Script::class       => Scripts::instance(),
            Style::class        => Styles::instance(),
            AdminScript::class  => AdminScripts::instance(),
            AdminStyle::class   => AdminStyles::instance(),
            EditorScript::class => EditorScripts::instance(),
            EditorStyle::class  => EditorStyles::instance(),
            default => throw new \InvalidArgumentException("Unsupported feature class: {$featureClass}"),
        };
    }
}