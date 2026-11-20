<?php

namespace MM\Meros\App\Content;

use MM\Meros\App\Models\Post;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;

use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Contracts\Features\Content\Concerns\UsesForms;

class Form extends PostType implements FormHost {
    use UsesForms;

    // =========================================================================
    // Configuration
    // =========================================================================

    protected function configure(): void {
        $this->public();
        $this->useBlockTemplate();
        $this->menuIcon('dashicons-feedback');

        $this->hasEditableForm()
            ->withActionsEditor()
            ->withFormSettings();
    }
}