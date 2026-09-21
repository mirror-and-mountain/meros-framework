<?php

namespace MM\Meros\App\Components\Blocks;

use MM\Meros\Contracts\Features\Components\DynamicBlock;

class TestBlock extends DynamicBlock {
    protected function configure(): void {
        $this->title('Test Block');
        $this->attributes([
            'test_text' => [
                'type' => 'string',
                'default' => 'test',
                'control' => [
                    'type' => 'text',
                    'placeholder' => 'Enter Text Here...'
                ]
            ],
            'test_long_text' => [
                'type' => 'string',
                'default' => 'Long Text',
                'control' => [
                    'type' => 'long-text'
                ]
            ],
            'test_date' => [
                'type' => 'string',
                'control' => [
                    'type' => 'datetime'
                ]
            ],
            'test_select' => [
                'type' => 'array',
                'default' => ['test_1'],
                'control' => [
                    'type' => 'multi-select',
                    'options' => [
                        'test_1' => 'Test 1',
                        'test_2' => 'Test 2'
                    ]
                ]
            ]
        ]);

        $this->renderCallback(function (array $attributes) {
           $date = $attributes['test_date'];

            return '<p>' . $date . '</p>';
        });

        $this->isSwitchable();
    }
}