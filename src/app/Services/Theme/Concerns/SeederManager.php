<?php 

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

trait SeederManager {
    private array $registeredSeeders = [];

    final public function registerSeederFromPath(string $path): bool 
}