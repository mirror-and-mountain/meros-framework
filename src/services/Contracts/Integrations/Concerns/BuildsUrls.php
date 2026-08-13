<?php 

namespace MM\Meros\Services\Contracts\Integrations\Concerns;

use Illuminate\Support\Str;

trait BuildsUrls {
    protected function buildRequestUrl(string $endpoint, array $queryParams = []): string {
        $hasVariables = Str::contains($endpoint, '{');

        if ($hasVariables) {
            $segments = explode('/', $endpoint);
            $segments = array_map(function ($segment) {
                if (Str::startsWith($segment, '{') && Str::endsWith($segment, '}')) {
                    $variableName = trim($segment, '{}');
                    return $this->settings($variableName);
                }
                return $segment;
            }, $segments);

            $endpoint = implode('/', $segments);
        }

        if (!empty($queryParams)) {
            $endpoint .= '?' . http_build_query($queryParams);
        }

        return $endpoint;
    }
}