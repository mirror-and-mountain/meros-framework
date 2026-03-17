<?php 

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

// Think about this (maybe add method to migration class for seeding?)

trait SeederManager {
    private array $registeredSeeders = [];

    final public function registerSeederFromPath(string $path): bool {

    }
}