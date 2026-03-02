<?php 

namespace MM\Meros\Components;

use Livewire\Component;

class Portal extends Component {
    public function render() {
        return view('meros::portal')->layout('meros::portal-layout');
    }
}