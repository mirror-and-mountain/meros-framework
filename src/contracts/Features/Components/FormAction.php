<?php

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;

abstract class FormAction extends Feature implements Registrable {
    /**
     * The unique name of the FormAction.
     *
     * @var string
     */
    protected string $name = '';

    use IsRegistrable;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('name', 'snake');
    }

    final protected function whenConfigured(): void {
        if ($this->getName() === '') {
            throw new \Exception('A form action must have a valid name.');
        }

        if ($this->getLabel() === '') {
            $this->label(Str::title(Str::replace('_', ' ', $this->getName())));
        }
    }


    // =========================================================================
    // Form Builder Controls
    // =========================================================================

    abstract public static function getFormBuilderControls(FieldGroup $controlGroup, array $rowData, array $repeaterData, array $formFields): FieldGroup;

    // =========================================================================
    // Handling
    // =========================================================================

    /**
     * The action handler which receives the submitted form's ID, the action's 
     * configuration and the submitted data array.
     *
     * @param integer $formId
     * @param array   $configuration
     * @param array   $submittedData
     *
     * @return boolean
     */
    abstract public function handle(int $formId, array $configuration, array $submittedData): bool;

    // =========================================================================
    // Attribute setters/getters
    // =========================================================================

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