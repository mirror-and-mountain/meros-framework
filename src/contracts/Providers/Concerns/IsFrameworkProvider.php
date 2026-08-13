<?php

namespace MM\Meros\Contracts\Providers\Concerns;

trait IsFrameworkProvider {
    use IsNonFrameworkProvider, ProvidesSettingsContainers;
}