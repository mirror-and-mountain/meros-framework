<?php 

namespace MM\Meros\App\Livewire;

use Livewire\Component;

class Test extends Component {
    public string $message = "Hello from Livewire!";

    public function changeMessage() {
        $this->message = "The message has been changed!";
    }

    public function render() {
        return view('meros::livewire.test')
            ->layout('meros::layouts.app');
    }
}