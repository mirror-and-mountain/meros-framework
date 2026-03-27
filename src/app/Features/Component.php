<?php 

namespace MM\Meros\App\Features;

use MM\Meros\App\Contracts\ComponentsRegistrar;

use Illuminate\Support\Str;
use MM\Meros\App\Facades\Registry;

class Component extends Feature {
    public string $class;
    public string $view;

    public function __construct(
        public ComponentsRegistrar $source
    ) {}

    public function make(array $config): self {
        $this->handle = Str::slug($config['handle'] ?? '', '_');
        
        $this->class  = $config['class'] ?? '';
        $this->view   = $config['view'] ?? '';

        Registry::add('components', $this);

        return $this;
    }
}

