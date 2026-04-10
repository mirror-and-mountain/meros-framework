<?php 

namespace MM\Meros\App\View\Components\Admin\Fields;

use Illuminate\View\Component;

class Checkbox extends Component {
    public function __construct(
        public string $id,
        public string $name,
        public bool   $value = false
    ) {}

    public function isChecked(): bool {
        return (bool) $this->value;
    }

    public function render() {
        return view('meros::components.admin.fields.checkbox');
    }
}