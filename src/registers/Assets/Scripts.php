<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\Script;
use MM\Meros\Contracts\Features\Assets\Groups\SiteDependencies;
use MM\Meros\Facades\Assets\Scripts as ScriptsFacade;

class Scripts extends Assets {
    final protected string $assetContract = Script::class;
    final protected string $dependencyGroupContract = SiteDependencies::class;
    final protected string $facadeClass = ScriptsFacade::class;
}