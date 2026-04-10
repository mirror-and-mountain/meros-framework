<?php 

namespace MM\Meros\App\PostTypes;

use MM\Meros\App\Support\PostType;

class Test extends PostType {
    public string $handle = 'tests';
    public bool $public = true;
    public bool $useBlockEditor = false;
}