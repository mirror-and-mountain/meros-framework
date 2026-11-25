<?php

namespace MM\Meros\Testing;

use MM\Meros\App\BaseTheme;
use MM\Meros\App\Providers\FrameworkServiceProvider;
use MM\Meros\Contracts\Features\Data\Table;
use MM\Meros\Contracts\Provider;
use MM\Meros\Facades\Framework;
use MM\Meros\Foundations\Application;
use MM\Meros\Foundations\Testing\WordPressTestCase as FoundationsWordPressTestCase;

/**
 * Boots the Framework with a fixture theme and package metadata, and provides table and block test helpers.
 */
abstract class WordPressTestCase extends FoundationsWordPressTestCase
{
    protected function createApplication(array $providers = []): Application
    {
        $root = dirname(__DIR__, 2);
        $this->files->ensureDirectoryExists($this->directory . '/vendor/mirror-and-mountain');
        $this->files->link($root, $this->directory . '/vendor/mirror-and-mountain/meros-framework');
        $this->files->ensureDirectoryExists($this->directory . '/config');
        $this->files->put($this->directory . '/config/theme.php', '<?php return ' . var_export([
            'theme_class' => TestTheme::class,
            'packages' => [],
        ], true) . ';');
        $packages = [['name' => 'livewire/livewire', 'extra' => ['laravel' => ['providers' => [\Livewire\LivewireServiceProvider::class]]]]];
        foreach ($this->packageRoots() as $name => $path) {
            $packages[] = json_decode($this->files->get($path . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->files->ensureDirectoryExists($this->directory . '/vendor/composer');
        $this->files->put($this->directory . '/vendor/composer/installed.json', json_encode(['packages' => $packages], JSON_THROW_ON_ERROR));
        // Packages may query core tables while booting, before WordPress's activation hooks run.
        add_action('meros_framework_configured', fn (Provider $framework) => $this->installProviderTables($framework));
        return parent::createApplication([FrameworkServiceProvider::class, ...$providers]);
    }

    /** @return array<string, string> Composer package names mapped to repository roots. */
    protected function packageRoots(): array
    {
        return [];
    }

    protected function installProviderTables(Provider $provider): void
    {
        $framework = Framework::get();
        $frameworkTables = $framework->tables()->all($framework);
        if ($frameworkTables->isEmpty()) {
            $frameworkTables = $framework->tables()->init();
        }
        $tracking = $frameworkTables->first(fn (Table $table) => $table->getName() === 'meros_migrations');
        $this->assertInstanceOf(Table::class, $tracking);
        $this->trackTable('meros_migrations');
        if (!$tracking->isInstalled()) {
            $tracking->install();
        }
        $this->assertSame('', $tracking->getLastError());
        $this->assertTrue($tracking->isInstalled());
        $tables = $provider->tables()->all($provider);
        if ($tables->isEmpty()) {
            $tables = $provider->tables()->init();
        }
        foreach ($tables as $table) {
            $this->trackTable($table->getName());
        }
        foreach ($tables as $table) {
            if (!$table->isInstalled()) {
                $table->install();
            }
            $this->assertSame('', $table->getLastError(), $table->getName());
            $this->assertTrue($table->isInstalled(), $table->getName());
        }
    }

    protected function assertBlockRegistered(string $name): \WP_Block_Type
    {
        $block = \WP_Block_Type_Registry::get_instance()->get_registered($name);
        $this->assertInstanceOf(\WP_Block_Type::class, $block);
        return $block;
    }
}

class TestTheme extends BaseTheme
{
    public bool $configured = false;

    public function configure(): void
    {
        $this->configured = true;
    }
}
