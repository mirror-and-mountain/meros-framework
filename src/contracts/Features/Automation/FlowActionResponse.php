<?php

namespace MM\Meros\Contracts\Features\Automation;

final class FlowActionResponse {
    /**
     * The output of the flow action.
     *
     * @var mixed
     */
    private mixed $output;

    /**
     * Indicates if the flow action resulted in an error.
     *
     * @var bool
     */
    private bool $hasError;

    /**
     * The error message if the flow action failed.
     *
     * @var string
     */
    private string $error;

    public function __construct(mixed $output, string $error = '') {
        $this->output   = $output;
        $this->error    = $error;
        $this->hasError = !empty($error);
    }

    /**
     * Returns the output of the flow action.
     *
     * @return mixed
     */
    public function output(): mixed {
        return $this->output;
    }

    /**
     * Indicates if the flow action resulted in an error.
     *
     * @return bool
     */
    public function hasError(): bool {
        return $this->hasError;
    }

    /**
     * Returns the error message if the flow action failed.
     *
     * @return string
     */
    public function getError(): string {
        return $this->error;
    }
}