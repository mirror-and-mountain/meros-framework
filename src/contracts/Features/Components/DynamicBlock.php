<?php

namespace MM\Meros\Contracts\Features\Components;

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

        foreach ($attributes as $key => $attribute) {
            $type  = $attribute['type'] ?? null;
            $label = isset($attribute['label']) && is_string($attribute['label']) ? $attribute['label'] : '';

            if ($type === null) {
                continue;
            }

            if (!isset($attribute['control'])) {
                continue;
            }

            $controlType = $type === 'boolean' ? 'toggle' : 'text';
        
            $this->attribute('merosControls', [
                'type' => 'object',
                'default' => 
                    array_merge(
                        $this->attributes['merosControls']['default'] ?? [], [
                            $key => [
                                'for'   => $key, 
                                'type'  => $controlType,
                                'label' => !empty($label) ? $label : ucfirst($key)
                            ]
                        ]
                    )
                ]
            );

            unset($this->attributes[$key]['control']);
        }

        $this->script->addAjaxData([
            'blocks' => [
                $this->getName() => [
                    'name'       => $this->getName(),
                    'title'      => $this->title,
                    'attributes' => $this->attributes
                ]
            ]
        ]);

        if (!is_callable($this->renderCallback)) {
            return;
        }

        add_action("wp_ajax_meros_dynamic_block_{$this->getName()}", function () {
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
                'html' => $this->sanitizeHtml(call_user_func($this->renderCallback, $attributes)),
            ]);
            exit;
        });

        parent::whenEnabled();
    }
}