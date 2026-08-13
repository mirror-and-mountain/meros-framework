<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\Script;
use MM\Meros\Facades\Assets\Scripts as ScriptsFacade;

class Scripts extends Assets {
    final protected string $assetClass = Script::class;
    final protected string $facadeClass = ScriptsFacade::class;

    // =========================================================================
    // Discovery: Coming soon
    // =========================================================================
}