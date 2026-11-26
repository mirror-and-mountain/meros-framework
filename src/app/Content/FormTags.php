<?php

namespace MM\Meros\App\Content;

use MM\Meros\Contracts\Features\Content\Taxonomy;

class FormTags extends Taxonomy {
    /**
     * Configures the shared, non-hierarchical taxonomy for forms and form templates.
     *
     * @return void
     */
    protected function configure(): void {
        $this->handle('form-tags');
        $this->label('Form Tag', 'Form Tags');
        $this->public()->hierarchical(false)->showInRest()->showAdminColumn();
        $this->showInMenu(false);
        $this->capabilities([
            'manage_terms' => 'read_forms',
            'edit_terms' => 'edit_forms',
            'delete_terms' => 'edit_forms',
            'assign_terms' => 'edit_forms',
        ]);
    }
}
