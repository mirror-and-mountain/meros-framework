<?php

namespace MM\Meros\Contracts\Registers\Concerns;

trait IsRegistrarMaker {
    use RegistersFeatures, MakesFeatures {
        RegistersFeatures::make insteadof MakesFeatures;
        MakesFeatures::make as private makeFeature;
    }
}