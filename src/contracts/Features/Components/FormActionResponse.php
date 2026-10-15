<?php

namespace MM\Meros\Contracts\Features\Components;

final class FormActionResponse {
    private array|true|string $response;

    public function __construct(array|true|string $response) {
        $this->response = $response;
    }

    public function response(): array|true|string {
        return $this->response;
    }
}