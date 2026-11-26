<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Support\Facades\Blade;
use MM\Meros\App\Framework;
use MM\Meros\Contracts\Provider;
use MM\Meros\Facades\Framework as FrameworkFacade;
use MM\Meros\Support\FormAssets;
use MM\Meros\Testing\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

final class FormAssetsTest extends TestCase {
    private Framework $framework;

    protected function setUp(): void {
        parent::setUp();
        $this->framework = (new ReflectionClass(Framework::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Provider::class, 'path'))->setValue(
            $this->framework,
            dirname(__DIR__, 2) . '/src/'
        );
        $this->setFrameworkUri('https://meros.test/framework/');
        FrameworkFacade::swap($this->framework);
        FormAssetsScriptRegistry::$instance = new FormAssetsScriptRegistry();
        $this->writeFile('scripts.blade.php', '@merosFormScripts()');
        $this->writeFile('styles.blade.php', '@merosFormStyles()');
        $this->app['view']->addNamespace('form-assets', $this->directory);
    }

    public function test_directives_render_all_assets_with_dependencies_first(): void {
        foreach ([
            'scripts' => ['index.js', '<script src="%s"></script>'],
            'styles' => ['style-index.css', '<link rel="stylesheet" href="%s">'],
        ] as $view => [$filename, $tag]) {
            $expected = [];
            foreach (['modal/', 'mforms/nice-forms/', 'mforms/tomselect/', 'mforms/quill/', 'mforms/'] as $directory) {
                if ($view === 'scripts' && $directory === 'mforms/quill/') {
                    $expected[] = FormAssetsScriptRegistry::LODASH_HTML;
                }
                $expected[] = sprintf(
                    $tag,
                    'https://meros.test/framework/resources/assets/wordpress/build/' . $directory . $filename
                        . '?ver=' . md5_file(dirname(__DIR__, 2) . '/src/resources/assets/wordpress/build/' . $directory . $filename)
                );
            }

            self::assertSame(implode("\n", $expected), $this->app['view']->make('form-assets::' . $view)->render());
        }
    }

    public function test_cached_directive_resolves_and_escapes_current_urls_at_render_time(): void {
        $directive = '@merosFormScripts()';
        self::assertStringNotContainsString('https://meros.test', Blade::compileString($directive));
        self::assertStringContainsString('https://meros.test/framework/', $this->app['view']->make('form-assets::scripts')->render());

        $this->setFrameworkUri('https://other.test/a&b/');
        $html = $this->app['view']->make('form-assets::scripts')->render();

        self::assertStringContainsString('https://other.test/a&amp;b/', $html);
        self::assertStringNotContainsString('https://meros.test/', $html);
    }

    public function test_missing_build_assets_fail_explicitly(): void {
        (new ReflectionProperty(Provider::class, 'path'))->setValue($this->framework, $this->directory . '/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Build the framework');
        $this->app->make(FormAssets::class)->styles();
    }

    public function test_cached_views_use_new_asset_urls_when_bundle_contents_change(): void {
        $buildPath = 'resources/assets/wordpress/build/mforms';
        $this->files->copyDirectory(dirname(__DIR__, 2) . '/src/' . $buildPath, $this->directory . '/' . $buildPath);
        $this->files->copyDirectory(
            dirname(__DIR__, 2) . '/src/resources/assets/wordpress/build/modal',
            $this->directory . '/resources/assets/wordpress/build/modal'
        );
        (new ReflectionProperty(Provider::class, 'path'))->setValue($this->framework, $this->directory . '/');
        $before = $this->app['view']->make('form-assets::scripts')->render();
        $bundle = $this->directory . '/' . $buildPath . '/index.js';
        $timestamp = filemtime($bundle);
        $newContents = 'console.log("updated bundle");';

        $this->writeFile($buildPath . '/index.js', $newContents);
        touch($bundle, $timestamp);
        $after = $this->app['view']->make('form-assets::scripts')->render();
        $expectedUrl = 'https://meros.test/framework/' . $buildPath . '/index.js?ver=' . md5($newContents);

        self::assertStringNotContainsString($expectedUrl, $before);
        self::assertStringContainsString($expectedUrl, $after);
    }

    public function test_external_dependencies_are_not_printed_twice(): void {
        $assets = $this->app->make(FormAssets::class);
        self::assertStringContainsString(FormAssetsScriptRegistry::LODASH_HTML, $assets->scripts());
        self::assertStringNotContainsString(FormAssetsScriptRegistry::LODASH_HTML, $assets->scripts());
    }

    public function test_unavailable_external_dependencies_fail_explicitly(): void {
        FormAssetsScriptRegistry::$instance->available = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to load form script dependencies: lodash');
        $this->app->make(FormAssets::class)->scripts();
    }

    private function setFrameworkUri(string $uri): void {
        (new ReflectionProperty(Provider::class, 'uri'))->setValue($this->framework, $uri);
    }
}

final class FormAssetsScriptRegistry {
    public const LODASH_HTML = '<script src="/wp-includes/js/dist/vendor/lodash.min.js"></script><script>window.lodash = _.noConflict();</script>';
    public static self $instance;
    public array $done = [];
    public array $available = ['lodash'];

    public function do_items(array $handles): void {
        foreach ($handles as $handle) {
            if (in_array($handle, $this->done, true) || !in_array($handle, $this->available, true)) {
                continue;
            }
            echo self::LODASH_HTML;
            $this->done[] = $handle;
        }
    }
}

namespace MM\Meros\Support;

function wp_scripts(): \MM\Meros\Tests\Unit\FormAssetsScriptRegistry {
    return \MM\Meros\Tests\Unit\FormAssetsScriptRegistry::$instance;
}
