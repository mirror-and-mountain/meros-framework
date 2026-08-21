<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\Style;
use MM\Meros\Contracts\Features\Assets\Groups\SiteDependencies;
use MM\Meros\Facades\Assets\Styles as StylesFacade;

class Styles extends Assets {
    final protected string $assetContract = Style::class;
    final protected string $dependencyGroupContract = SiteDependencies::class;
    final protected string $facadeClass = StylesFacade::class;
}