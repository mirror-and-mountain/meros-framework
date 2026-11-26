<?php

namespace MM\Meros\App\Security\Capabilities;

use MM\Meros\Contracts\Features\Security\Capability;

class EditForms extends Capability {
    protected function configure(): void {
        $this->name('edit_forms');
        $this->label('Edit Forms');
        $this->description('Create and edit forms.');
        $this->roles(['editor']);
        $this->userConfigurable();
        $this->assignableToDefaultRoles();
    }
}
