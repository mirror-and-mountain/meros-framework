<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\AdminStyle;
use MM\Meros\Facades\Assets\AdminStyles as AdminStylesFacade;

class AdminStyles extends Assets {
    final protected string $assetClass = AdminStyle::class;
    final protected string $facadeClass = AdminStylesFacade::class;

    // =========================================================================
    // Discovery: Coming soon
    // =========================================================================
}