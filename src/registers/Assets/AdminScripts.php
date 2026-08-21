<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\AdminScript;
use MM\Meros\Contracts\Features\Assets\Groups\AdminDependencies;
use MM\Meros\Facades\Assets\AdminScripts as AdminScriptsFacade;

class AdminScripts extends Assets {
    final protected string $assetContract = AdminScript::class;
    final protected string $dependencyGroupContract = AdminDependencies::class;
    final protected string $facadeClass = AdminScriptsFacade::class;
}