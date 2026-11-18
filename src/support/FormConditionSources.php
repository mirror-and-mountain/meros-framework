<?php

namespace MM\Meros\Support;

use InvalidArgumentException;
use UnexpectedValueException;

final class FormConditionSources {
    public const SIGNED_IN = '__user_signed_in';

    /**
     * Get the definitions of form condition sources.
     *
     * @return array
     */
    public static function definitions(): array {
        $sources = apply_filters('meros_form_condition_sources', [
            self::SIGNED_IN => [
                'label'   => 'User signed in',
                'values'  => ['true' => 'Signed in', 'false' => 'Signed out'],
                'resolve' => static fn (): bool => (bool) apply_filters('mforms_user_signed_in', is_user_logged_in()),
            ],
        ]);

        if (!is_array($sources)) {
            throw new InvalidArgumentException('Form condition sources must be an array.');
        }

        foreach ($sources as $name => $source) {
            if (!is_string($name) || !preg_match('/^__[a-z][a-z0-9_]*$/D', $name) ||
                !is_array($source) ||
                !is_string($source['label'] ?? null) || !is_array($source['values'] ?? null) ||
                $source['values'] === [] || array_key_exists('', $source['values']) ||
                array_filter($source['values'], static fn ($label) => !is_string($label)) !== [] ||
                !is_callable($source['resolve'] ?? null)) {
                throw new InvalidArgumentException("Invalid form condition source: {$name}.");
            }
        }

        return $sources;
    }

    /**
     * Get the choices for form condition sources.
     *
     * @return array
     */
    public static function choices(): array {
        return array_map(static fn (array $source): array => [
            'label'  => $source['label'],
            'values' => (object) $source['values'],
        ], self::definitions());
    }

    /**
     * Get the resolved values for form condition sources.
     *
     * @return array
     */
    public static function values(): array {
        $values = [];
        foreach (self::definitions() as $name => $source) {
            $value = $source['resolve']();
            if ($value === null) {
                $values[$name] = null;
                continue;
            }
            if (!self::allowsValue($source, $value)) {
                throw new UnexpectedValueException("Form condition source {$name} returned an unconfigured value.");
            }
            $values[$name] = self::valueKey($value);
        }
        return $values;
    }

    /**
     * Determine if the given value is allowed for the specified form condition source.
     *
     * @param array $source
     * @param mixed $value
     *
     * @return boolean
     */
    public static function allowsValue(array $source, mixed $value): bool {
        return (is_string($value) || is_bool($value)) &&
            array_key_exists(self::valueKey($value), $source['values']);
    }

    /**
     * Get the key representation of the given value.
     *
     * @param string|boolean $value
     *
     * @return string
     */
    private static function valueKey(string|bool $value): string {
        return is_bool($value) ? ($value ? 'true' : 'false') : $value;
    }
}
