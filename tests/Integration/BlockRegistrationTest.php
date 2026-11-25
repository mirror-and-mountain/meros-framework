<?php

namespace MM\Meros\Tests\Integration;

use MM\Meros\Contracts\Features\Components\Block;
use MM\Meros\Testing\FeatureProvider;
use MM\Meros\Testing\WordPressTestCase;

final class BlockRegistrationTest extends WordPressTestCase
{
    public function test_dynamic_blocks_reach_the_wordpress_registry_and_render(): void
    {
        Block::__make(new FeatureProvider($this->directory), function (Block $block) {
            $block->name('probe');
            $block->title('Probe');
            $block->renderCallback(fn () => '<p>Probe block</p>');
        });
        do_action('meros_framework_booted');
        do_action('init');
        $this->assertBlockRegistered('test-provider/probe');
        $this->assertSame('<p>Probe block</p>', render_block([
            'blockName' => 'test-provider/probe', 'attrs' => [], 'innerBlocks' => [],
            'innerHTML' => '', 'innerContent' => [],
        ]));
    }
}
