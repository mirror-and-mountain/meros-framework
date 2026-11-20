<?php

namespace MM\Meros\Contracts\Features\Components\Fields;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;

abstract class Input extends Field {
    protected bool   $showsIcon = false;
    protected string $iconPosition = 'left';
    
    final protected function init(): void {
        parent::init();
        
        $this->view('meros::forms.fields.input');
        $this->setSerializableProperties(['showsIcon', 'iconPosition']);
    }

    /**
     * Sets the 'type' attribute of the input field.
     * 
     * Should be called in concrete implementations of this class to set the input type
     * in their configure() method.
     *
     * @param string $type
     *
     * @return void
     */
    protected function inputType(string $type): void {
        $this->attribute('type', $type);
    }

    /**
     * Sets the field to show an icon in the field input, if supported.
     *
     * @param bool|string $show
     * @param string      $position
     *
     * @return static
     */
    public function showIcon(string|bool $show = 'left', string $position = 'left'): static {
        if (is_string($show)) {
            $position = $show;
            $show = true;
        }

        if ($this->supports('icon') && $show) {
            $this->class("icon-{$position}");
            $this->showsIcon = true;
            $this->iconPosition = $position;
        }

        if ($this->supports('icon') && !$show) {
            $this->removeClass("icon-{$this->iconPosition}");
            $this->showsIcon = false;
        }

        return $this;
    }

    /**
     * Adds builder controls specific to the input field.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param array      $rowData The data for the current row in the form builder.
     *
     * @return FieldGroup
     */
    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $default = $rowData['form_data']['defaultValue'] ?? null;
        $dynamic = is_string($default) && Str::startsWith($default, '{{') && Str::endsWith($default, '}}');
        $group->row(function ($row) use ($locked) {
            $row->field('text', [
                'name'        => 'placeholder',
                'label'       => 'Placeholder',
                'readonly'    => $locked,
                'description' => "The field's placeholder text."
            ]);
        });

        $group->field('checkbox', [
            'name' => '__builder_use_dynamic_default',
            'label' => 'Use Dynamic Default Value',
            'toggle' => true,
            'disabled' => $locked,
            'default' => $dynamic,
        ], false);
        
        $literal = $group->field(Str::lower(class_basename(static::class)), [
            'name' => 'defaultValue',
            'label' => 'Default Value',
            'readonly' => $locked,
        ], false, true);

        $literal->showWhen([['__builder_use_dynamic_default', '=', false]]);

        $types = array_values(array_filter(\MM\Meros\Support\FormDynamicValues::TYPES,
            fn (string $type): bool => $literal->isCompatibleWithDataType($type)));

        $group->field('text', function (Field $field) use ($locked, $dynamic, $default, $types) {
            $field->name('__builder_dynamic_default');
            $field->label('Dynamic Default Value');
            $field->description('Enter a complete dynamic value, for example {{user_email}}.');
            $field->readonly($locked);
            $field->default($dynamic ? $default : '');
            $field->required();
            $field->attribute('pattern', '\{\{[^{}]*[^\s{}][^{}]*\}\}');
            $field->attribute('data-dynamic-value-types', json_encode($types, JSON_THROW_ON_ERROR));
            $field->showWhen([['__builder_use_dynamic_default', '=', true]]);
        }, false);

        return $group;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Ensures that the 'type' attribute is set to 'text' if it hasn't been set already.
     *
     * @param array $properties
     *
     * @return array
     */
    protected function filterSerializedProperties(array $properties): array {
        if (!collect($this->attributes)->has('type')) {
            $this->inputType('text');
        }

        return $properties;
    }
}