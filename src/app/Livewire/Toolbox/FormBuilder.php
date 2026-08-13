<?php

namespace MM\Meros\App\Livewire\Toolbox;

use Livewire\Component;

class FormBuilder extends Component {
    public string $id = '';
    public string $title = '';
    public string $description = '';
    
    public array $rows = [];

    public function mount() {
        // Initialization logic for the FormBuilder component
    }

    public function render() {
        return view('meros::livewire.toolbox.form-builder.index')
            ->layout('meros::livewire.toolbox.form-builder.layout');
    }
}