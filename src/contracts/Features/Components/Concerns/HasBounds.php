<?php

namespace MM\Meros\Contracts\Features\Components\Concerns;

use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\App\Components\Fields\Number;

/**
 * Trait HasBounds
 *
 * Provides functionality for components that have minimum and maximum bounds.
 */
trait HasBounds {
    protected int|float|null $min = null;
    protected int|float|null $max = null;

    /**
     * Configures the minimum and maximum bounds for the component.
     *
     * @param boolean $count
     *
     * @return void
     */
    protected function configureBounds(bool $count = true): void {
        $this->setSerializableProperties(['min', 'max']);
        foreach (['min', 'max'] as $name) {
            if (!array_key_exists($name, $this->passedProps)) continue;
            $value = $this->passedProps[$name];
            if ($value === null || $value === '') {
                $this->$name = null;
                $this->removeAttribute($count ? "data-{$name}-count" : $name);
                continue;
            }
            if (!is_numeric($value) || !is_finite((float) $value) ||
                ($count && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)) {
                throw new \InvalidArgumentException("Invalid {$name} bound for field '{$this->getName()}'.");
            }
            $this->$name = $count ? (int) $value : $value + 0;
            $this->attribute($count ? "data-{$name}-count" : $name, $this->$name);
        }
        if ($this->min !== null && $this->max !== null && $this->min > $this->max) {
            throw new \InvalidArgumentException("Minimum exceeds maximum for field '{$this->getName()}'.");
        }
    }

    /**
     * Adds controls for configuring the minimum and maximum bounds within a field group.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param string     $unit
     * @param boolean    $count
     * @param boolean    $multipleOnly
     *
     * @return void
     */
    protected static function addBoundsControls(
        FieldGroup $group, bool $locked, string $unit, bool $count = true, bool $multipleOnly = false
    ): void {
        $group->row(function ($row) use ($locked, $unit, $count, $multipleOnly) {
            foreach (['min' => 'Minimum', 'max' => 'Maximum'] as $name => $label) {
                $attributes = ['step' => $count ? '1' : 'any', 'data-builder-bound' => $name];
                if ($count) $attributes['min'] = 0;
                $row->field('number', function (Number $field) use ($multipleOnly) {
                    if ($multipleOnly) $field->showWhen([['allowsMultiple', '=', true]]);
                }, [
                    'name' => $name,
                    'label' => "{$label} {$unit}",
                    'readonly' => $locked,
                    'attributes' => $attributes,
                ]);
            }
        });
    }
}
