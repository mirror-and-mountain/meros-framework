<?php

namespace MM\Meros\App\Security\Capabilities;

use MM\Meros\Contracts\Features\Security\Capability;

class ReadFormResponses extends Capability {
    protected function configure(): void {
        $this->name('read_form_responses');
        $this->label('Read Form Responses');
        $this->description('View form responses.');
        $this->userConfigurable();
    }
}
