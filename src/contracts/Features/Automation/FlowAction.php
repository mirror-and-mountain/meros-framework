<?php

namespace MM\Meros\Contracts\Features\Automation;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;

class FlowAction extends Feature implements Registrable, Makeable {
    /**
     * The unique name of the FlowAction.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Optional input schema that defines the structure of the payload
     * passed to the action's run method.
     *
     * @var array
     */
    protected array $inputSchema = [];

    /**
     * Optional output schema used to validate the action's output
     * via the run method's returned FlowActionResponse.
     *
     * @var array
     */
    protected array $outputSchema = [];

    private ?Closure $runner = null;

    use IsRegistrable, IsMakeable;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('name', 'snake');
    }

    final protected function whenConfigured(): void {
        if ($this->getName() === '') {
            $this->name(class_basename($this));
        }

        if ($this->getLabel() === '') {
            $this->label(Str::title(Str::replace('_', ' ', $this->getName())));
        }
    }

    // =========================================================================
    // Handling
    // =========================================================================

    /**
     * Runs the action which receives the submitted form's ID, the action's 
     * configuration and the submission payload.
     *
     * @param array   $input
     *
     * @return FlowActionResponse
     * @throws \RuntimeException if the flow's run method cannot return a valud FlowActionResponse.
     */
    public function run(array $input): FlowActionResponse {
        if ($this->runner === null) {
            throw new \RuntimeException("A flow action must have a runner set or its 'run' method overridden to return a valid Flow ActionResponse.");
        }
        return call_user_func($this->runner, $input);
    }

    // =========================================================================
    // Attribute setters/getters
    // =========================================================================

    /**
     * Sets the action's input schema.
     *
     * @param array $schema
     *
     * @return static
     */
    final public function setInputSchema(array $schema): static {
        $this->inputSchema = $schema;
        return $this;
    }

    final public function addInput(
        string $name, 
        string $type, 
        ?string $fieldType = null,
        ?string $label = null,
        ?string $description = null,
        bool $required = false
    ): static {
        $this->inputSchema[$name] = [
            'type'        => Str::lower($type),
            'field_type'  => $fieldType,
            'label'       => $label,
            'description' => $description,
            'required'    => $required
        ];

        return $this;
    }

    /**
     * Sets the action's output schema.
     *
     * @param array $schema
     *
     * @return static
     */
    final public function setOutputSchema(array $schema): static {
        $this->outputSchema = $schema;
        return $this;
    }

    /**
     * Sets the action's runner which is called by the run() method by default.
     *
     * @param Closure $runner
     *
     * @return static
     */
    final public function runner(Closure $runner): static {
        $this->runner = $runner;
        return $this;
    }
    
    /**
     * Validates and returns the action's input schema.
     *
     * @return array
     */
    public function getInputSchema(): array {
        return $this->inputSchema;
    }

    /**
     * Returns the action's output schema.
     *
     * @return array
     */
    public function getOutputSchema(): array {
        return $this->outputSchema;
    }

    /**
     * Sets the name of the FormAction.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $this->setIdentifier($name);
        return $this;
    }

    /**
     * Retrieves the form action's name.
     *
     * @return string
     */
    final public function getName(): string {
        return $this->name;
    }
}