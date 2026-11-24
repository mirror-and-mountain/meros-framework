<?php

namespace MM\Meros\App\Security\Roles;

use MM\Meros\Contracts\Features\Security\Role;

class FormManager extends Role {
    protected function configure(): void {
        $this->handle('form_manager');
        $this->label('Form Manager');
        $this->capabilities([
            'read_forms' => true,
            'edit_forms' => true,
            'read_form_responses' => true,
            'edit_form_responses' => true,
        ]);
        $this->userConfigurable();
    }
}
