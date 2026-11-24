<?php

namespace MM\Meros\App\Security\Capabilities;

use MM\Meros\Contracts\Features\Security\Capability;

class EditFormResponses extends Capability {
    protected function configure(): void {
        $this->name('edit_form_responses');
        $this->label('Edit Form Responses');
        $this->description('Edit form responses.');
        $this->userConfigurable();
    }
}
