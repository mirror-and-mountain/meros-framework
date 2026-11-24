<?php

namespace MM\Meros\App\Security\Roles;

use MM\Meros\Contracts\Features\Security\Role;

class FormReader extends Role {
    protected function configure(): void {
        $this->handle('form_reader');
        $this->label('Form Reader');
        $this->capabilities([
            'read_forms' => true,
            'read_form_responses' => true,
        ]);
        $this->userConfigurable();
    }
}
