<?php

namespace MM\Meros\Contracts\Features\Integrations\Concerns;

trait Abstracts {
    abstract protected function settings(string $setting = '', bool $refresh = false): mixed;
    abstract public function getCurrentEnvironment(bool $refresh = false): string;
}