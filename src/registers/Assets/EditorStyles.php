<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\EditorStyle;
use MM\Meros\Contracts\Features\Assets\Groups\EditorDependencies;
use MM\Meros\Facades\Assets\EditorStyles as EditorStylesFacade;

class EditorStyles extends Assets {
    final protected string $assetContract = EditorStyle::class;
    final protected string $dependencyGroupContract = EditorDependencies::class;
    final protected string $facadeClass = EditorStylesFacade::class;
}