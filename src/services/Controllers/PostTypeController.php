<?php

namespace MM\Meros\Services\Controllers;

use MM\Meros\Services\Contracts\FeatureController;
use MM\Meros\Services\Concerns\HasPostTypes;

abstract class PostTypeController extends FeatureController {
    use HasPostTypes;
}