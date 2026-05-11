<?php

namespace MM\Meros\App\Livewire\Toolbox;

use Livewire\Component;

class Index extends Component {

    public function mount() {

    }

    public function render() {
        return view('meros::livewire.toolbox.index')
            ->layout('meros::livewire.toolbox.layout', ['title' => 'Meros Toolbox']);
    }
}