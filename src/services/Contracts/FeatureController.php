<?php 

namespace MM\Meros\Services\Contracts;

abstract class FeatureController {

    protected function __construct(
        protected FeatureProvider $authority
    ) {}

    final public static function init(FeatureProvider $authority): static {
        $instance = new static($authority);
        $instance->load();
        return $instance;
    }

    abstract protected function load(): void;

    /**
     * Returns the handle of the authority that this controller is associated with.
     *
     * @return string
     */
    public function getHandle(): string {
        return $this->authority->getHandle();
    }

    /**
     * Returns the name of the authority that this controller is associated with.
     *
     * @return string
     */
    public function getName(): string {
        return $this->authority->getName();
    }


    /**
     * Resolves the authority for registering features.
     *
     * @return FeatureProvider
     */
    final protected function resolveAuthority(): FeatureProvider {
        return $this->authority;
    }
}