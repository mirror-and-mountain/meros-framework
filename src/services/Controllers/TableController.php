<?php

namespace MM\Meros\Services\Controllers;

use MM\Meros\Services\Contracts\FeatureController;
use MM\Meros\Services\Concerns\HasInstallers;

abstract class TableController extends FeatureController {
    use HasInstallers;
}