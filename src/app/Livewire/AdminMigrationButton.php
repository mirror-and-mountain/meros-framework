<?php

namespace MM\Meros\App\Livewire;

use Livewire\Component;

class AdminMigrationButton extends Component {
    private string $feature;
    private string $slug;
    private string $label;
    private string $btnClass;

    public function mount(string $feature, string $slug, string $label) {
        $this->feature = $feature;
        $this->slug = $slug;
        $this->label = $label;

        if ($label === 'Up To Date') {
            $this->btnClass = 'meros-admin-migration-up-to-date-button';
        } else if ($label === 'Install') {
            $this->btnClass = 'meros-admin-migration-install-button';
        } else if ($label === 'Update') {
            $this->btnClass = 'meros-admin-migration-update-button';
        }
    }

    public function render() {
        return view('meros::admin-migration-button', [
            'label' => $this->label,
            'btnClass' => 'meros-admin-migration-button ' . $this->btnClass . ' button button-primary'
        ]);
    }
}

