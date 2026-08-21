<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Features\Assets\AdminStyle;
use MM\Meros\Contracts\Features\Assets\Groups\AdminDependencies;
use MM\Meros\Facades\Assets\AdminStyles as AdminStylesFacade;

class AdminStyles extends Assets {
    final protected string $assetContract = AdminStyle::class;
    final protected string $dependencyGroupContract = AdminDependencies::class;
    final protected string $facadeClass = AdminStylesFacade::class;
}