<?php

namespace MM\Meros\Contracts\Features\Assets\Groups;

class EditorDependencies extends DependenciesGroup {
    protected function configure(): void {
        $this->area('editor');
    }
}