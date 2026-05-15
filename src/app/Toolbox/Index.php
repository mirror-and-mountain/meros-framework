<?php

namespace MM\Meros\App\Toolbox;

use Livewire\Component;

class Index extends Component {

    public function mount() {

    }

    public function render() {
        return view('meros::toolbox.index')
            ->layout('meros::toolbox.layout', ['title' => 'Meros Toolbox']);
    }
}