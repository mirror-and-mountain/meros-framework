<?php

namespace MM\Meros\Contracts\Features\Assets\Groups;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

abstract class DependenciesGroup extends AssetGroup {
    final protected bool $registerWhenEnabled = true;
}