<?php

namespace MM\Meros\Support\Integrations\CRM;

use MM\Meros\App\Integrations\ExternalModels\GenericResource;
use MM\Meros\Services\Contracts\Integration;
use MM\Meros\Facades\Integrations;

final class SyncJobRunner {
    public function __construct(
        protected SyncValueResolver $valueResolver
    ) {
    }

    public function runMany(array $jobs, array $submission, array $context = []): array {
        $results = [];

        foreach ($jobs as $index => $jobData) {
            try {
                $job = $jobData instanceof SyncJob ? $jobData : SyncJob::fromArray((array) $jobData);

                if (!$job->isValid()) {
                    $results[] = [
                        'ok' => false,
                        'index' => $index,
                        'error' => 'Invalid sync job configuration.',
                    ];
                    continue;
                }

                $response = $this->run($job, $submission, $context);

                $results[] = [
                    'ok' => true,
                    'index' => $index,
                    'response' => $response,
                ];
            } catch (\Throwable $exception) {
                report($exception);

                $results[] = [
                    'ok' => false,
                    'index' => $index,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function run(SyncJob $job, array $submission, array $context = []): array {
        $integration = Integrations::get($job->integrationHandle);

        if (!$integration instanceof Integration) {
            throw new \RuntimeException('Integration not found for handle: ' . $job->integrationHandle);
        }

        if ($integration->getCategory() !== 'crm') {
            throw new \RuntimeException('Integration is not a CRM integration: ' . $job->integrationHandle);
        }

        $payload = $this->buildPayload($job, $submission);

        /** @var GenericResource $resource */
        $resource = app(GenericResource::class);
        $resource->integration($job->integrationHandle);

        if ($job->connectionLabel !== '') {
            $resource->using($job->connectionLabel);
        }

        if ($job->environment !== '') {
            $resource->usingEnvironment($job->environment);
        }

        $method = strtoupper($job->method);
        $endpoint = $job->resolveEndpoint();

        $response = match ($method) {
            'POST' => $resource->asJson()->payload($payload)->post($endpoint),
            'PUT' => $resource->asJson()->payload($payload)->put($endpoint),
            'PATCH' => $resource->asJson()->payload($payload)->patch($endpoint),
            'DELETE' => $resource->asJson()->payload($payload)->delete($endpoint),
            default => $resource->asJson()->payload($payload)->request($method, $endpoint),
        };

        if ($response->failed()) {
            throw new \RuntimeException('CRM sync request failed: ' . $response->body());
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    private function buildPayload(SyncJob $job, array $submission): array {
        $payload = [];

        foreach ($job->mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $target = trim((string) ($mapping['target'] ?? $mapping['target_field'] ?? ''));

            if ($target === '') {
                continue;
            }

            $value = $this->valueResolver->resolve($mapping, $submission);

            if ($value === null || $value === '') {
                continue;
            }

            $payload[$target] = $value;
        }

        return $payload;
    }
}
