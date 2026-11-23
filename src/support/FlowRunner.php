<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\RichText;
use MM\Meros\App\Components\Fields\Select;

use MM\Meros\Facades\Automation\FlowActions;
use MM\Meros\Facades\Components\Fields;
use MM\Meros\Facades\Support\FormDynamicValues;

class FlowRunner {
    /**
     * Reserved dynamic values for flow actions.
     */
    protected const RESERVED_DYNAMIC_VALUES = ['user_id', 'user_display_name'];

    /**
     * The list of actions to be executed in the flow.
     *
     * @var array
     */
    private array $actions;

    /**
     * The payload that triggers the flow.
     *
     * @var array
     */
    private array $triggerPayload;

    /**
     * The list of actions that have been completed in the flow.
     *
     * @var array
     */
    private array $completedActions = [];

    /**
     * The last error message encountered during the flow execution.
     *
     * @var string
     */
    private string $lastError = '';

    /**
     * The success message to be displayed if the flow completes successfully.
     *
     * @var string
     */
    private string $successMessage = '';

    /**
     * Indicates whether an error should be shown as a success message.
     *
     * @var bool
     */
    private bool $showErrorAsSuccess = false;

    public function __construct(array $actions, array $triggerPayload) {
        $this->actions        = $actions;
        $this->triggerPayload = $triggerPayload;
    }

    /**
     * Executes the flow by running each action in sequence.
     *
     * @return bool Whether the flow ran successfully or not.
     */
    public function run(): bool {
        $previousSucceeded = true;

        foreach ($this->actions as $actionConfig) {
            $actionName = $this->getActionName($actionConfig);
            $actionId   = $this->getActionId($actionConfig);

            $this->completedActions[$actionId] = [
                'name'    => $actionName,
                'status'  => '',
                'error'   => '',
                'inputs'  => '',
                'outputs' => '',
            ];

            try {
                $settings = $this->getActionConfiguration($actionConfig);
            } catch (\UnexpectedValueException $exception) {
                $this->lastError = $exception->getMessage();
                $this->completedActions[$actionId]['status'] = 'error';
                $this->completedActions[$actionId]['error']  = $this->lastError;
                return false;
            }

            $runWhen = $settings['run_when'] ?? 'success';
            if (!in_array($runWhen, ['success', 'failure', 'always'], true)) {
                $this->lastError = 'Invalid run condition for action: ' . $actionName;
                $this->completedActions[$actionId]['status'] = 'error';
                $this->completedActions[$actionId]['error']  = $this->lastError;
                return false;
            }

            if (($runWhen === 'success' && !$previousSucceeded) ||
                ($runWhen === 'failure' && $previousSucceeded)
            ) {
                $this->completedActions[$actionId]['status'] = 'skipped';
                continue;
            }

            $errorBehaviour = $this->getActionErrorHandlingBehaviour($settings);
            $errorAction    = $errorBehaviour['action'] ?? 'continue';
            $errorMessage   = $errorBehaviour['message'] ?? '';

            if (!in_array($errorAction, ['continue', 'stop_and_show_error', 'stop_and_show_success'], true)) {
                $this->lastError = 'Invalid error behaviour for action: ' . $actionName;
                $this->completedActions[$actionId]['status'] = 'error';
                $this->completedActions[$actionId]['error']  = $this->lastError;
                return false;
            }

            $error = null;
            try {
                $action = $this->getActionInstance($actionConfig);
                if (!($action instanceof FlowAction)) {
                    $error = 'Invalid action instance for action: ' . $actionName;
                } else {
                    $inputPayload = $this->getActionInputPayload($action, $actionConfig);
                    if (!is_array($inputPayload)) {
                        $error = $inputPayload;
                    } else {
                        $this->completedActions[$actionId]['inputs'] = $inputPayload;
                        $result = $action->run($inputPayload);

                        if ($result->hasError()) {
                            $error = 'Flow action error for action: ' . $actionName . '. Message: ' . $result->getError();
                        } else {
                            $this->triggerPayload[$actionId] = $result->output();
                            $this->completedActions[$actionId]['outputs'] = $result->output();
                            $this->completedActions[$actionId]['status'] = 'success';
                        }
                    }
                }
            } catch (\Throwable $e) {
                $error = 'Exception occurred while running action: ' . $actionName . '. Message: ' . $e->getMessage();
            }

            if ($error !== null) {
                $previousSucceeded = false;
                $this->lastError = $errorMessage === '' ? $error : $errorMessage . ': ' . $error;
                $this->completedActions[$actionId]['status'] = 'error';
                $this->completedActions[$actionId]['error']  = $this->lastError;

                if ($errorAction === 'continue') {
                    continue;
                }

                $this->showErrorAsSuccess = $errorAction === 'stop_and_show_success';
                $this->successMessage = $this->showErrorAsSuccess ? $errorMessage : '';
                return false;
            }

            $previousSucceeded = true;
        }

        return $previousSucceeded;
    }

    /**
     * Returns the last error message encountered during the flow execution.
     *
     * @return string
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * Indicates whether an error should be shown as a success message.
     *
     * @return boolean
     */
    public function getErrorAsSuccess(): bool {
        return $this->showErrorAsSuccess;
    }

    /**
     * Returns the success message to be displayed if the flow completes successfully.
     *
     * @return string
     */
    public function getSuccessMessage(): string {
        return $this->successMessage;
    }

    /**
     * Returns an array of all actions that have been completed during the flow execution.
     *
     * @return array
     */
    public function getCompletedActions(): array {
        return $this->completedActions;
    }

    /**
     * Returns the name of the action from the action configuration.
     *
     * @param array $actionConfig
     * @return string
     */
    private function getActionName(array $actionConfig): string {
        return $actionConfig['action'] ?? '';
    }

    /**
     * Returns the unique ID of the action from the action configuration.
     *
     * @param array $actionConfig
     * @return string
     */
    private function getActionId(array $actionConfig): string {
        return $actionConfig['action_unique_id'] ?? '';
    }

    /**
     * Returns an instance of the action based on the action configuration.
     *
     * @param array $actionConfig
     * @return FlowAction|null
     */
    private function getActionInstance(array $actionConfig): ?FlowAction {
        $actionName = $this->getActionName($actionConfig);

        if (!is_string($actionName) || $actionName === '') {
            return null;
        }

        $registeredAction = FlowActions::getRegisteredFeature($actionName);

        if (!is_array($registeredAction) || 
            !array_key_exists('provider', $registeredAction) ||
            !array_key_exists('class', $registeredAction) || 
            !array_key_exists('initialised', $registeredAction)
        ) {
            return null;
        }

        $provider = $registeredAction['provider'];

        if (!($provider instanceof FeatureProvider)) {
            return null;
        }

        $class = $registeredAction['class'];
        $initialised = $registeredAction['initialised'];

        if (!$initialised) {
            $action = FlowActions::checkout($provider->get())
                ->makeFrom($class);
        } else {
            $action = FlowActions::get($actionName);
        }

        if (!($action instanceof FlowAction)) {
            return null;
        }

        return $action;
    }

    /**
     * Returns the decoded editor configuration for an action.
     */
    private function getActionConfiguration(array $actionConfig): array {
        $payload = $actionConfig['__form_data'] ?? [];
        if (is_string($payload)) {
            $payload = $payload === '' ? [] : json_decode($payload, true);
        }

        if (!is_array($payload)) {
            throw new \UnexpectedValueException('Invalid configuration for action: ' . $this->getActionName($actionConfig));
        }

        return $payload;
    }

    private function getActionErrorHandlingBehaviour(array $payload): array {
        $action = $payload['error_behaviour'] ?? 'continue';
        return [
            'action' => $action,
            'message' => $action === 'stop_and_show_success'
                ? ($payload['success_message'] ?? '')
                : ($payload['error_message'] ?? ''),
        ];
    }

    /**
     * Returns the input payload for the given action based on its configuration.
     *
     * @param FlowAction $action
     * @param array $actionConfig
     *
     * @return array|string
     */
    private function getActionInputPayload(FlowAction $action, array $actionConfig): array|string {
        $inputSchema  = $action->getInputSchema();
        $payloadError = "An input payload is required for form action: " . $action->getName() . '.';

        if ($inputSchema === []) {
            return [];
        }

        if (!array_key_exists('__form_data', $actionConfig)) {
            return $payloadError;
        }

        $payload = $this->getActionConfiguration($actionConfig);

        if ($payload === []) {
            return $payloadError;
        }

        return $this->parseActionInputPayload($action,$inputSchema, $payload);
    }

    /**
     * Parses the input payload for the given action based on its input schema.
     *
     * @param FlowAction $action
     * @param array $schema
     * @param array $payload
     *
     * @return array|string
     */
    private function parseActionInputPayload(FlowAction $action, array $schema, array $payload): array|string {
        $parsed = [];
        $rows = $payload['action_inputs'] ?? [];
        if (!is_array($rows)) {
            return "Invalid input configuration for form action: " . $action->getName() . '.';
        }
        $inputs = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['input_name'] ?? null) || isset($inputs[$row['input_name']])) {
                return "Invalid input configuration for form action: " . $action->getName() . '.';
            }
            $inputs[$row['input_name']] = $row;
        }

        foreach ($schema as $key => $config) {
            $validDataTypes = explode('|', $config['type']);
            $inputError    = "Form action '" . $action->getName() . "' requires a valid input value for input: " . $key . '.';
            $isStandalone = FlowAction::inputUsesStandaloneField($config);
            $lockedDynamic = !$isStandalone && ($config['readonly'] ?? false) === true && ($config['dynamic'] ?? false) === true;
            $row = $inputs[$key] ?? null;
            if (!$isStandalone && !$lockedDynamic && !is_array($row)) return $inputError;
            $valueIsDynamic = !$isStandalone && ($lockedDynamic || ($row['use_dynamic_value'] ?? false) === true);
            if ($valueIsDynamic) {
                $expression = $lockedDynamic ? ($config['default'] ?? null) : ($row['input_value'] ?? null);
                if (!is_string($expression)) return $inputError;
                if ($lockedDynamic && !str_contains($expression, '{{')) {
                    $value = $this->resolveDynamicValue($expression);
                } elseif (preg_match('/^\s*\{\{\s*([^{}]+?)\s*\}\}\s*$/', $expression, $match)) {
                    $value = $this->resolveDynamicValue($match[1]);
                } else {
                    $value = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', function ($match) use ($inputError) {
                        $resolved = $this->resolveDynamicValue($match[1]);
                        if (!is_scalar($resolved)) throw new \UnexpectedValueException($inputError);
                        return (string) $resolved;
                    }, $expression);
                    if (str_contains($value, '{{') || str_contains($value, '}}')) return $inputError;
                }
            } else {
                $value = $isStandalone ? ($payload[Str::snake($key) . '_value'] ?? null) : ($row['input_value'] ?? null);
                $field = null;
                if (isset($config['field_callback']) && $this->containsDynamicToken($value)) {
                    if (!is_callable($config['field_callback'])) {
                        throw new \InvalidArgumentException("Invalid field callback for action input '{$key}'.");
                    }
                    $fieldType = $config['field_type'] ?? FlowAction::inferInputFieldType($config['type']);
                    $field = Fields::checkout($action->getProvider())->makeFrom(
                        $fieldType, function (Field $field) use ($config, $key, $isStandalone) {
                            $field->name($isStandalone ? Str::snake($key) . '_value' : 'input_value');
                            $field->label($isStandalone ? ($config['label'] ?? $key) : 'Input Value');
                            if (($config['description'] ?? null) !== null) $field->description($config['description']);
                            if ($field instanceof Select && is_array($config['options'] ?? null)) $field->options($config['options']);
                            if ($field instanceof Select && ($config['type'] ?? null) === 'array.scalar') $field->multiple();
                            $dynamicDefault = ($config['allow_dynamic_value'] ?? true) &&
                                ($config['dynamic'] ?? (!($config['readonly'] ?? false) && !array_key_exists('default', $config)));
                            if (array_key_exists('default', $config) && ($isStandalone || !$dynamicDefault)) {
                                $field->default($config['default']);
                            }
                            if ($config['required'] ?? false) $field->required();
                            if ($config['readonly'] ?? false) $field->readonly();
                            ($config['field_callback'])($field);
                            $field->name($isStandalone ? Str::snake($key) . '_value' : 'input_value');
                        }
                    );
                    $value = $this->parseConfiguredFieldValue($field, $value, $inputError);
                }
                if (!$field?->__suggestsDynamicValues() &&
                    ($config['field_type'] ?? null) === 'richtext' && ($config['allow_dynamic_value'] ?? true) === true &&
                    is_string($value) && str_contains($value, '{{')) {
                    $value = FormDynamicValues::parseHtml($value, fn (string $token) => $this->resolveDynamicValue($token));
                }
                if (is_string($value) && !in_array('string', $validDataTypes, true) && is_numeric($value)) {
                    if (in_array('integer', $validDataTypes, true) && preg_match('/^[+-]?\d+$/', $value)) {
                        $value = (int) $value;
                    } elseif (in_array('number', $validDataTypes, true) || in_array('double', $validDataTypes, true)) {
                        $value = (float) $value;
                    }
                }
            }
            $type = gettype($value);
            $valid = in_array($type, $validDataTypes, true)
                || ($type === 'integer' && in_array('number', $validDataTypes, true))
                || ($type === 'double' && in_array('number', $validDataTypes, true))
                || ($type === 'array' && in_array('array.object', $validDataTypes, true)
                    && count(array_filter($value, 'is_array')) === count($value))
                || ($type === 'array' && in_array('array.scalar', $validDataTypes, true)
                    && count(array_filter($value, 'is_scalar')) === count($value));
            if (!$valid) return $inputError;
            $parsed[$key] = $value;
        }

        return $parsed;
    }

    private function containsDynamicToken(mixed $value): bool {
        if (is_string($value)) return str_contains($value, '{{') || str_contains($value, '}}');
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsDynamicToken($item)) return true;
            }
        }
        return false;
    }

    private function parseConfiguredFieldValue(Field $field, mixed $value, string $inputError): mixed {
        if ($field instanceof Repeater && is_array($value)) {
            foreach ($value as &$row) {
                if (!is_array($row)) throw new \UnexpectedValueException($inputError);
                foreach ($field->getFields(true) as $column) {
                    $name = $column->getName();
                    if (array_key_exists($name, $row)) {
                        $row[$name] = $this->parseConfiguredFieldValue($column, $row[$name], $inputError);
                    }
                }
            }
            unset($row);
            return $value;
        }

        if (!$field->__suggestsDynamicValues() || !is_string($value) ||
            (!str_contains($value, '{{') && !str_contains($value, '}}'))) return $value;
        if ($field instanceof RichText) {
            return FormDynamicValues::parseHtml($value, fn (string $token) => $this->resolveDynamicValue($token));
        }
        if (preg_match('/^\s*\{\{\s*([^{}]+?)\s*\}\}\s*$/', $value, $match)) {
            $resolved = $this->resolveDynamicValue($match[1]);
            if ($resolved === null) throw new \UnexpectedValueException($inputError);
            return $resolved;
        }
        $parsed = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', function ($match) use ($inputError) {
            $resolved = $this->resolveDynamicValue($match[1]);
            if (!is_scalar($resolved)) throw new \UnexpectedValueException($inputError);
            return (string) $resolved;
        }, $value);
        if (str_contains($parsed, '{{') || str_contains($parsed, '}}')) throw new \UnexpectedValueException($inputError);
        return $parsed;
    }

    /**
     * Resolves a dynamic value to its actual value based on the current context.
     *
     * @param string $dynamicValue
     *
     * @return mixed
     */
    protected function resolveDynamicValue(string $dynamicValue): mixed {
        $dynamicValue = trim(str_replace(['{{', '}}'], '', $dynamicValue));

        // Handle dynamic values sourced from other actions.
        if (Str::contains($dynamicValue, '_source_')) {
            $parts = explode('_source_', $dynamicValue);
            if (count($parts) === 2) {
                $sourceActionId = $parts[1];
                $dynamicValue = $parts[0];

                return $this->triggerPayload[$sourceActionId][$dynamicValue] ?? null;
            }

            return null;
        }

        // Keep existing field references ahead of newly available context tokens.
        if (in_array($dynamicValue, static::RESERVED_DYNAMIC_VALUES, true)) {
            return FormDynamicValues::resolve($dynamicValue);
        }

        $fieldValues = $this->triggerPayload['data'] ?? [];

        if (array_key_exists($dynamicValue, $fieldValues) && is_array($fieldValues[$dynamicValue])) {
            return $fieldValues[$dynamicValue]['user_value'] ?? null;
        }

        return FormDynamicValues::resolve($dynamicValue);
    }
}