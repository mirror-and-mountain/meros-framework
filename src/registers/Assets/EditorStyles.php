<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\EditorStyle;
use MM\Meros\Facades\Assets\EditorStyles as EditorStylesFacade;

class EditorStyles extends Assets {
    final protected string $assetClass = EditorStyle::class;
    final protected string $facadeClass = EditorStylesFacade::class;

    // =========================================================================
    // Discovery: Coming soon
    // =========================================================================
}