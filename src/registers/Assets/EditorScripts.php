<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\EditorScript;
use MM\Meros\Contracts\Features\Assets\Groups\EditorDependencies;
use MM\Meros\Facades\Assets\EditorScripts as EditorScriptsFacade;

class EditorScripts extends Assets {
    final protected string $assetContract = EditorScript::class;
    final protected string $dependencyGroupContract = EditorDependencies::class;
    final protected string $facadeClass = EditorScriptsFacade::class;
}