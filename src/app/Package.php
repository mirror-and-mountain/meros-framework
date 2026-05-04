<?php

namespace MM\Meros\App;

use MM\Meros\Services\Contracts\FeatureProvider;

abstract class Package extends FeatureProvider {
    /**
     * Indicates whether the package is enabled.
     *
     * @var boolean
     */
    private bool $enabled = false;

    final public function __construct(
        string $name = '',
        string $path = '',
        string $uri  = ''
    ) {
        $enabled = get_option('meros_framework_settings')['packages'][$this->getHandle() . '_enable'] ?? false;
        $this->enabled = (bool) $enabled;

        if ($this->enabled) {
            parent::__construct($name, $path, $uri);
        }

        else {
            $this->setIdentity($name, $path, $uri);
        }
    }

    /**
     * Gets the instance of the package.
     *
     * @return Package
     */
    final public function get(): Package {
        return $this;
    }

    /**
     * Returns whether the package is enabled.
     *
     * @return bool
     */
    final public function isEnabled(): bool {
        return $this->enabled;
    }
}
