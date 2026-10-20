<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Automation\FlowActionResponse;

use MM\Meros\Facades\Automation\FlowActions;

class FlowRunner {    
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
     * @return boolean
     */
    public function run(): bool {
        foreach ($this->actions as $actionConfig) {
            $actionName = $this->getActionName($actionConfig);
            $actionId   = $this->getActionId($actionConfig);
            $action     = $this->getActionInstance($actionConfig);

            $this->completedActions[$actionId] = [
                'name'    => $actionName,
                'status'  => '',
                'error'   => '',
                'inputs'  => '',
                'outputs' => '',
            ];

            if (!($action instanceof FlowAction)) {
                $this->lastError = 'Invalid action instance for action: ' . $actionName;
                $this->completedActions[$actionId]['status'] = 'error';
                $this->completedActions[$actionId]['error']  = $this->lastError;
                return false;
            }

            $errorBehaviour = $this->getActionErrorHandlingBehaviour($actionConfig);
            $errorAction    = $errorBehaviour['action'] ?? 'continue';
            $errorMessage   = $errorBehaviour['message'] ?? '';

            if ($errorAction !== 'continue') {
                $this->showErrorAsSuccess = $errorAction === 'stop_and_show_success';
            }

            if ($errorAction === 'stop_and_show_success') {
                $this->successMessage = $errorMessage;
            }

            $inputPayload = $this->getActionInputPayload($action, $actionConfig);

            if (!is_array($inputPayload)) {
                $this->lastError = $errorMessage . ': ' . $inputPayload;
                $this->completedActions[$actionId]['status'] = 'error';
                $this->completedActions[$actionId]['error']  = $this->lastError;
                return false;
            }

            $this->completedActions[$actionId]['inputs'] = $inputPayload;

            try {
                $result = $action->run($inputPayload);
            } catch (\Throwable $e) {
                $this->lastError =  $errorMessage . ': ' . 'Exception occurred while running action: ' . $actionName . '. Message: ' . $e->getMessage();
                $this->completedActions[$actionId]['error']  = $this->lastError;
                
                if ($errorAction === 'continue') {
                    $this->completedActions[$actionId]['status'] = 'skipped';
                    continue;
                }

                $this->completedActions[$actionId]['status'] = 'error';
                return false;
            }

            if ($result instanceof FlowActionResponse) {
                $isError = $result->hasError();
                if ($isError) {
                    $this->lastError = $errorMessage . ': ' . 'Form action error for action: ' . $actionName . '. Message: ' . $result->getError();
                    $this->completedActions[$actionId]['error']  = $this->lastError;

                    if ($errorAction === 'continue') {
                        $this->completedActions[$actionId]['status'] = 'skipped';
                        continue;
                    }

                    $this->completedActions[$actionId]['status'] = 'error';
                    return false;

                } else {
                    $this->triggerPayload[$actionId] = $result->output();
                    $this->completedActions[$actionId]['outputs'] = $result->output();
                    $this->completedActions[$actionId]['status'] = 'success';
                }
            }
        }

        return true;
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
     * Returns the error handling behaviour for the given action configuration.
     *
     * @param array $actionConfig
     *
     * @return array
     */
    private function getActionErrorHandlingBehaviour(array $actionConfig): array {
        if (!array_key_exists('__form_data', $actionConfig)) {
            return [
                'action' => 'continue'
            ];
        }

        $payload = $actionConfig['__form_data'];

        if (is_string($payload) && $payload !== '') {
            $payload = json_decode($payload, true);
        } else {
            $payload = [];
        }

        if (array_key_exists('error_behaviour', $payload)) {
            return [
                'action'  => $payload['error_behaviour'],
                'message' => $payload['error_message'] ?? $payload['success_message'] ?? ''
            ];
        }

        return [
            'action' => 'continue'
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

        $payload = $actionConfig['__form_data'];

        if (is_string($payload) && $payload !== '') {
            $payload = json_decode($payload, true);
        } else {
            $payload = [];
        }

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

        foreach ($schema as $key => $config) {
            $requiredDataType = $config['type'];

            if (Str::contains($requiredDataType, '|')) {
                $validDataTypes = explode('|', $requiredDataType);
            } else {
                $validDataTypes = [$requiredDataType];
            }

            $inputError    = "Form action '" . $action->getName() . "' requires a valid input value for input: " . $key . '.';
            $payloadHasKey = array_key_exists($key . '_label', $payload);

            if ($payloadHasKey === false) {
                return $inputError;
            }

            $valueIsValid = function ($value) use ($validDataTypes) {
                return in_array(gettype($value), $validDataTypes) === true;
            };

            $valueIsDynamic = 
                array_key_exists('use_dynamic_value_' . $key, $payload) && 
                $payload['use_dynamic_value_' . $key] === true;
            
            if ($valueIsDynamic) {
                $dynamicValue = array_key_exists($key . '_dynamic_value', $payload)
                    ? $payload[$key . '_dynamic_value']
                    : null;

                if (!is_string($dynamicValue)) {
                    return $inputError;
                } else {
                    $value = $this->resolveDynamicValue($dynamicValue);

                    if ($valueIsValid($value) === false) {
                        return $inputError;
                    }

                    $parsed[$key] = $value;
                }
            }

            else {
                $value = array_key_exists($key . '_value', $payload)
                    ? $payload[$key . '_value']
                    : null;

                if ($valueIsValid($value) === false) {
                    return $inputError;
                }

                $parsed[$key] = $value;
            }
        }

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
        // Handle dynamic values for the current user.
        if (Str::startsWith($dynamicValue, 'user_')) {
            $userId = get_current_user_id();
            switch ($dynamicValue) {
                case 'user_id':
                    return $userId;
                case 'user_display_name':
                    $user = get_user_by('ID', $userId);
                    if ($user instanceof \WP_User) {
                        return $user->display_name;
                    } else {
                        return null;
                    }
                default: 
                    return null;
            }
        }

        // Handle dynamic values sourced from other actions.
        if (Str::contains($dynamicValue, '_source_')) {
            $parts = explode('_source_', $dynamicValue);
            if (count($parts) === 2) {
                $sourceActionId = $parts[1];
                $dynamicValue = $parts[0];

                if (array_key_exists($dynamicValue, $this->triggerPayload[$sourceActionId] ?? [])) {
                    return $this->triggerPayload[$sourceActionId][$dynamicValue] ?? null;   
                }

                return null;
            }

            return null;
        }

        // Handle dynamic values for the current form's field values.
        $fieldValues = $this->triggerPayload['data'] ?? [];

        if (array_key_exists($dynamicValue, $fieldValues) && is_array($fieldValues[$dynamicValue])) {
            return $fieldValues[$dynamicValue]['user_value'] ?? null;
        }

        return null;
    }
}