<?php

namespace MM\Meros\Support;

use Illuminate\Support\Collection;
use MM\Meros\Contracts\Register;

final class Registers {
    /**
     * A collection of Register instances.
     *
     * @var Collection<Register>
     */
    protected Collection $registers;

    /**
     * Constructor for the Registers class.
     */
    public function __construct() {
        $this->registers = new Collection();
    }

    /**
     * Adds a Register instance to the collection.
     *
     * @param Register $register The Register instance to add.
     */
    public function add(Register $register): void {
        $this->registers->push($register);
    }

    /**
     * Retrieves all registered Register instances.
     *
     * @return Collection<Register> A collection of all registered Register instances.
     */
    public function all(): Collection {
        return $this->registers;
    }

    /**
     * Retrieves a register that that serves the required class definition.
     *
     * @param string $requiredClassDefinition The class definition to match.
     * 
     * @return Register|null The matching register or null if not found.
     */
    public function getRegisterFor(string $requiredClassDefinition): ?Register {
        return $this->registers->first(function (Register $register) use ($requiredClassDefinition) {
            return $register->getDefinition() === $requiredClassDefinition;
        });
    }
}