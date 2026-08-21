<?php

namespace MM\Meros\Contracts\Features\Assets\Groups;

class SiteDependencies extends DependenciesGroup {
    protected function configure(): void {
        $this->area('site');
    }
}