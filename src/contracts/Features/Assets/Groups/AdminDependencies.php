<?php

namespace MM\Meros\Contracts\Features\Assets\Groups;

class AdminDependencies extends DependenciesGroup {
    protected function configure(): void {
        $this->area('admin');
    }
}