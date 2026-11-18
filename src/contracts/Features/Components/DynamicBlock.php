<?php

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Assets\Asset;
use MM\Meros\App\Assets\DynamicBlock as DynamicBlockAsset;
use MM\Meros\App\Components\Fields\Lookup;
use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Facades\Assets;
use MM\Meros\Facades\Framework;

use MM\Meros\Contracts\Features\Concerns\SanitizesHtml;

class DynamicBlock extends Block {
    private Asset $script;

    use SanitizesHtml;

    final protected function init(): void {
        parent::init();        
        $this->attribute('merosDynamicBlock', [
            'type' => 'boolean',
            'default' => true
        ]);

        $script = Assets::checkout(Framework::get())
            ->get('meros-dynamic-block');

        if ($script === null) {
            $script = Assets::checkout(Framework::get())
                ->preload(DynamicBlockAsset::class)
                ->register();
        }

        if (!($script instanceof Asset)) {
            throw new \RuntimeException("Couldn't load the meros-dynamic-block script");
        }

        $this->editorScript($script->getHandle());
        $this->script = $script;
    }

    final protected function whenEnabled(): void {
        $attributes = $this->attributes;
        $repeaterNonces = [];

        foreach ($attributes as $key => $attribute) {
            $type  = $attribute['type'] ?? null;
            $label = isset($attribute['label']) && is_string($attribute['label']) ? $attribute['label'] : '';
            $callback = $attribute['control_callback'] ?? null;
            unset($this->attributes[$key]['control_callback']);

            if ($type === null) {
                continue;
            }

            if (!isset($attribute['control'])) {
                continue;
            }

            $controlType = $attribute['control'] === true && $type === 'array' && $callback !== null
                ? 'repeater'
                : $this->getControlType($type, $attribute['control']);

            $controlConfig = [
                'for'   => $key,
                'type'  => $controlType,
                'label' => !empty($label) ? $label : ucfirst($key)
            ];

            if ($controlType === 'repeater') {
                if ($type !== 'array' || !is_callable($callback)) {
                    throw new \InvalidArgumentException("Repeater control '{$key}' requires an array attribute and a callable control_callback.");
                }

                $action = $this->registerRepeaterControl($key, $controlConfig['label'], $callback);
                $controlConfig['ajaxAction'] = $action;
                $repeaterNonces[$action] = wp_create_nonce($action);
            }

            if (
                is_array($attribute['control']) &&
                array_key_exists('options', $attribute['control'])
            ) {
                $options = $attribute['control']['options'];
                $normalisedOptions = [];

                foreach ($options as $value => $label) {
                    if (!is_string($label)) {
                        continue;
                    }

                    if (is_int($value)) {
                        $value = $label;
                    }

                    $normalisedOptions[] = ['value' => $value, 'label' => $label];
                }

                $controlConfig['options'] = $normalisedOptions;
            }

            if (is_array($attribute['control']) &&
                array_key_exists('placeholder', $attribute['control']) &&
                is_string($attribute['control']['placeholder'])
            ) {
                $controlConfig['placeholder'] = $attribute['control']['placeholder'];
            }

            $this->attribute('merosControls', [
                'type' => 'object',
                'default' =>
                    array_merge(
                        $this->attributes['merosControls']['default'] ?? [],
                        [$key => $controlConfig]
                    )
            ]);

            unset($this->attributes[$key]['control']);
        }

        $ajaxAction = sanitize_key('meros_dynamic_block_' . Str::replace(['-', '/'], '_', $this->getName()));
        $this->script->addAjaxData([
            'blocks' => [
                $this->getName() => [
                    'name'        => $this->getName(),
                    'title'       => $this->title,
                    'icon'        => $this->icon,
                    'description' => $this->description,
                    'parent'      => $this->parent,
                    'allowedBlocks' => $this->allowedBlocks,
                    'ajaxAction'  => $ajaxAction,
                    'repeaterNonces' => $repeaterNonces,
                    'attributes'  => $this->attributes
                ]
            ]
        ]);

        if (!is_callable($this->renderCallback)) {
            return;
        }

        add_action("wp_ajax_{$ajaxAction}", function () {
            $rawAttributes = $_POST['attributes'] ?? '[]';
            $attributes = [];

            if (is_string($rawAttributes) && $rawAttributes !== '') {
                $decoded = json_decode(wp_unslash($rawAttributes), true);

                if (is_array($decoded)) {
                    $attributes = $decoded;
                } else {
                    wp_send_json_error([
                        'message' => 'Invalid block attributes payload.',
                        'error' => json_last_error_msg(),
                    ]);
                    exit;
                }
            }

            wp_send_json_success([
                'html' => $this->sanitizeHtml($this->render($attributes)),
            ]);
            exit;
        });

        parent::whenEnabled();
    }

    /**
     * Registers a repeater control for the dynamic block.
     *
     * @param string   $attribute The attribute name for the repeater.
     * @param string   $label     The label for the repeater control.
     * @param callable $configure A callback to configure the repeater.
     *
     * @return string The AJAX action name for the repeater.
     */
    private function registerRepeaterControl(string $attribute, string $label, callable $configure): string {
        $action = 'meros_block_repeater_' . md5($this->getName() . ':' . $attribute);

        add_action("wp_ajax_{$action}", function () use ($action, $label, $configure) {
            if (!check_ajax_referer($action, 'nonce', false) ||
                !(current_user_can('edit_posts') || current_user_can('edit_pages') || current_user_can('edit_theme_options'))) {
                wp_send_json_error(['message' => __('You are not allowed to use this repeater.', 'meros-theme')], 403);
                return;
            }

            $repeater = Repeater::__make_from_registered($this->getProvider(), function (Repeater $repeater) use ($label, $configure) {
                $repeater->label($label)->hideLabel();
                $configure($repeater);
            });

            $mode = $_POST['mode'] ?? 'render';
            $fields = $repeater->getFields(true);

            if ($mode === 'edit_row' || ($mode === 'lookup' && isset($_POST['row']))) {
                $rows = $this->decodeRepeaterRows($_POST['value'] ?? null);
                $index = is_string($_POST['row'] ?? null)
                    ? filter_var($_POST['row'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                    : false;

                if ($index === false || !isset($rows[$index]) ||
                    !is_array($rows[$index]['form_data'] ?? [])) {
                    wp_send_json_error(['message' => __('Invalid repeater row.', 'meros-theme')], 400);
                    return;
                }

                $form = $repeater->prepareEditForm($rows[$index], $rows);
                if ($form === null) {
                    wp_send_json_error(['message' => __('This repeater row has no edit form.', 'meros-theme')], 400);
                    return;
                }

                $fields = $form->getFields(true);
                if ($mode === 'edit_row') {
                    $this->markRepeaterLookupFields($fields);
                    $form->onSubmit('meros_repeater_form_submit')->hideSubmitButton(true);
                    wp_send_json_success(['html' => $form->html()]);
                    return;
                }
            }

            if ($mode === 'lookup') {
                $name = $_POST['field'] ?? null;
                $search = $_POST['search'] ?? null;
                if (is_string($name) && is_string($search)) {
                    $name = wp_unslash($name);
                    foreach ($fields as $field) {
                        if ($field instanceof Lookup && $field->getName() === $name) {
                            $results = [];
                            foreach ($field->resolve(wp_unslash($search)) as $value => $text) {
                                $results[] = ['value' => (string) $value, 'text' => $text];
                            }
                            wp_send_json_success(['results' => $results]);
                            return;
                        }
                    }
                }

                wp_send_json_error(['message' => __('Unknown repeater lookup field.', 'meros-theme')], 400);
                return;
            }

            $rows = $this->decodeRepeaterRows($_POST['value'] ?? null);
            if ($mode !== 'render' || $rows === null) {
                wp_send_json_error(['message' => __('Invalid repeater value.', 'meros-theme')], 400);
                return;
            }

            $this->markRepeaterLookupFields($fields);
            $repeater->default($rows);
            wp_send_json_success(['html' => $repeater->html()]);
        });

        return $action;
    }

    /**
     * Decodes the raw JSON string for repeater rows into an associative array.
     *
     * @param mixed $raw
     *
     * @return array|null
     */
    private function decodeRepeaterRows(mixed $raw): ?array {
        if (!is_string($raw)) {
            return null;
        }

        $json = wp_unslash($raw);
        $rows = json_decode($json);
        if (!is_array($rows) || array_filter($rows, fn ($row) => !$row instanceof \stdClass)) {
            return null;
        }

        return json_decode($json, true);
    }

    /**
     * Marks all lookup fields within the repeater with a data attribute for identification.
     *
     * @param iterable $fields
     *
     * @return void
     */
    private function markRepeaterLookupFields(iterable $fields): void {
        foreach ($fields as $field) {
            if ($field instanceof Lookup) {
                $field->attribute('data-meros-block-lookup-field', $field->getName());
            }
        }
    }

    /**
     * Retrieves the control type for a given control or infers one from the given dataType.
     *
     * @param string     $dataType
     * @param array|bool $control
     *
     * @return string
     */
    private function getControlType(string $dataType, array|bool $control): string {
        if (is_array($control) && isset($control['type']) && is_string($control['type'])) {
            return $control['type'];
        }

        return match ($dataType) {
            'string'  => 'text',
            'boolean' => 'toggle',
            default => 'text',
        };
    }
}