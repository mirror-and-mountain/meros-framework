<?php

namespace MM\Meros\App\Content;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Contracts\Features\Content\Concerns\UsesForms;

class FormTemplate extends PostType implements FormHost {
    use UsesForms;

    protected function configure(): void {
        $this->public();
        $this->handle('form-template');
        $this->label('Form Template', 'Form Templates');
        $this->menuIcon('dashicons-feedback');
        $this->hasFormType('form')->withoutTemplate()->withActionsEditor();
    }
}