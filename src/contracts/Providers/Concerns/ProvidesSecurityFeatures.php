<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Security\Capability;
use MM\Meros\Contracts\Features\Security\Role;
use MM\Meros\Registers\Security\Capabilities;
use MM\Meros\Registers\Security\Roles;

trait ProvidesSecurityFeatures {
    use Abstracts;

    final protected function roles(string $handle = ''): Role|Roles|null {
        return $this->resolveFeatureRequestFor(Role::class, $handle);
    }

    final protected function capabilities(string $name = ''): Capability|Capabilities|null {
        return $this->resolveFeatureRequestFor(Capability::class, $name);
    }
}
