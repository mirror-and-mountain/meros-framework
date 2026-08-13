<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\EditorScript;
use MM\Meros\Facades\Assets\EditorScripts as EditorScriptsFacade;

class EditorScripts extends Assets {
    final protected string $assetClass = EditorScript::class;
    final protected string $facadeClass = EditorScriptsFacade::class;

    // =========================================================================
    // Discovery: Coming soon
    // =========================================================================
}