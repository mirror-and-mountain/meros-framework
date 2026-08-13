<?php

namespace MM\Meros\Services\Controllers;

use MM\Meros\Services\Contracts\FeatureController;
use MM\Meros\Services\Concerns\HasSettings;

abstract class SettingsController extends FeatureController {
    use HasSettings;
}