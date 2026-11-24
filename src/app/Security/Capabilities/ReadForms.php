<?php

namespace MM\Meros\App\Security\Capabilities;

use MM\Meros\Contracts\Features\Security\Capability;

class ReadForms extends Capability {
    protected function configure(): void {
        $this->name('read_forms');
        $this->label('Read Forms');
        $this->description('View forms.');
        $this->userConfigurable();
    }
}
