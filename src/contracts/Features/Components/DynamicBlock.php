<?php

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Assets\Asset;
use MM\Meros\App\Assets\DynamicBlock as DynamicBlockAsset;

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
        $types = [];

        foreach ($attributes as $key => $attribute) {
            $type  = $attribute['type'] ?? null;
            $label = isset($attribute['label']) && is_string($attribute['label']) ? $attribute['label'] : '';

            if ($type === null) {
                continue;
            }

            if (!isset($attribute['control'])) {
                continue;
            }

            $controlType = $this->getControlType($type, $attribute['control']);

            $controlConfig = [
                'for'   => $key,
                'type'  => $controlType,
                'label' => !empty($label) ? $label : ucfirst($key)
            ];

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
                    'name'       => $this->getName(),
                    'title'      => $this->title,
                    'ajaxAction' => $ajaxAction,
                    'attributes' => $this->attributes
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
            'string' => 'text',
            'boolean' => 'toggle',
            default => 'text',
        };
    }
}