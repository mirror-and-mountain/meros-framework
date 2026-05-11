<?php

namespace MM\Meros\App\Livewire\Toolbox;

use Livewire\Component;

class FormBuilder extends Component {

    public $message = 'Hello, World!';

    public function mount() {
        
    }

    public function changeMessage() {
        $this->message = 'Message changed!';
    }

    public function render() {
        return view('meros::livewire.toolbox.form-builder')
            ->layout('meros::livewire.toolbox.layout', ['title' => 'Form Builder']);
    }
}