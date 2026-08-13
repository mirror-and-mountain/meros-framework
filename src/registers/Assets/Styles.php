<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\Style;
use MM\Meros\Facades\Assets\Styles as StylesFacade;

class Styles extends Assets {
    final protected string $assetClass = Style::class;
    final protected string $facadeClass = StylesFacade::class;

    // =========================================================================
    // Discovery: Coming soon
    // =========================================================================
}