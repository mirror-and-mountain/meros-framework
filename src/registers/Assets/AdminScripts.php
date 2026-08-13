<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\AdminScript;
use MM\Meros\Facades\Assets\AdminScripts as AdminScriptsFacade;

class AdminScripts extends Assets {
    final protected string $assetClass = AdminScript::class;
    final protected string $facadeClass = AdminScriptsFacade::class;

    // =========================================================================
    // Discovery: Coming soon
    // =========================================================================
}