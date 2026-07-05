<?php

namespace MM\Meros\Support\Integrations\CRM;

use MM\Meros\Support\MergeFields;

final class SyncValueResolver {
    public function resolve(array $mapping, array $submission): mixed {
        $source = trim((string) ($mapping['source'] ?? ''));
        $fallback = $mapping['fallback'] ?? null;

        if ($source === '') {
            return $fallback;
        }

        if (str_starts_with($source, 'form:')) {
            $fieldName = substr($source, 5);
            $value = $submission[$fieldName] ?? null;

            return $this->normalize($value, $fallback);
        }

        if (str_starts_with($source, 'merge:')) {
            $mergeKey = substr($source, 6);
            $resolved = MergeFields::get()->resolve($mergeKey, 'string');

            return $this->normalize($resolved, $fallback);
        }

        if (str_starts_with($source, 'value:')) {
            return substr($source, 6);
        }

        return $this->normalize($source, $fallback);
    }

    private function normalize(mixed $value, mixed $fallback = null): mixed {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return $value;
    }
}
