<?php

namespace MM\Meros\Services\Controllers;

use MM\Meros\Services\Contracts\FeatureController;
use MM\Meros\Services\Concerns\HasFields;

abstract class FieldsController extends FeatureController {
    use HasFields;
}