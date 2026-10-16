<?php

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;

abstract class FormAction extends Feature implements Registrable {
    /**
     * The unique name of the FormAction.
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
    protected static array $inputSchema = [];

    /**
     * Optional output schema used to validate the action's output
     * via the run method's returned FormActionResponse.
     *
     * @var array
     */
    protected static array $outputSchema = [];

    /**
     * Internal rules for validating the action's inputSchema if it is set.
     *
     * @var array
     */
    private static array $inputSchemaRules = [
        'type' => [
            'type'           => 'string',
            'required'       => true,
            'allowed_values' => ['string', 'text', 'integer', 'boolean']
        ],
        'options' => [
            'type'     => 'array',
            'required' => false
        ],
        'required' => [
            'type'     => 'boolean',
            'required' => false
        ],
        'allow_dynamic_value' => [
            'type'     => 'boolean',
            'required' => false
        ],
        'default' => [
            'type'     => 'mixed',
            'required' => false
        ]
    ];

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
    // Handling
    // =========================================================================

    /**
     * Runs the action which receives the submitted form's ID, the action's 
     * configuration and the submission payload.
     *
     * @param integer $formId
     * @param array   $configuration
     * @param array   $input
     *
     * @return FormActionResponse
     */
    abstract public function run(
        int $formId, 
        array $configuration, 
        array $input
    ): FormActionResponse;

    // =========================================================================
    // Attribute setters/getters
    // =========================================================================
    
    /**
     * Validates and returns the action's input schema.
     *
     * @return array
     */
    public static function getInputSchema(): array {
        return static::$inputSchema;
    }

    /**
     * Returns the action's output schema.
     *
     * @return array
     */
    public static function getOutputSchema(): array {
        return static::$outputSchema;
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