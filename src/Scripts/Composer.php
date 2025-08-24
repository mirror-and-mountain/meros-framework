<?php

namespace MM\Meros\Scripts;

use Composer\Script\Event;
use Composer\Composer as ComposerInstance; // Alias to avoid conflict with class name
use Composer\IO\IOInterface;
use MM\Meros\Helpers\PluginInfo;

/**
 * Helpers to install theme features, extensions and plugins.
 */
class Composer
{
    /**
     * Configuration returned from config/theme.php.
     *
     * @var array
     */
    private static array $themeConfig;

    /**
     * Features defined in the theme's config.
     *
     * @var array
     */
    private static array $features;

    /**
     * Extensions defined in the theme's config.
     *
     * @var array
     */
    private static array $extensions;

    /**
     * Plugins defined in the theme's config.
     *
     * @var array
     */
    private static array $plugins;

    /**
     * The root path of the Composer project (and assumed theme root).
     *
     * @var string
     */
    private static string $projectRoot;

    /**
     * The path to the 'vendor' directory.
     *
     * @var string
     */
    private static string $vendorDir;

    /**
     * The path to the 'stubs' directory for Meros scripts.
     *
     * @var string
     */
    private static string $stubDir;

    /**
     * Initializes static properties based on the Composer event.
     * This should be called at the beginning of any public static method that
     * needs path information.
     *
     * @param ComposerInstance $composer
     * @param IOInterface $io
     * @return void
     */
    private static function initialisePaths(ComposerInstance $composer, IOInterface $io): void
    {
        $vendorDir   = realpath($composer->getConfig()->get('vendor-dir'));
        $directories = Utils::getDirectories( $vendorDir );

        if ( $directories !== false ) {
            self::$vendorDir   = $directories['vendorDir'] ?? '';
            self::$projectRoot = $directories['projectRoot'] ?? '';
            self::$stubDir     = $directories['stubDir'] ?? '';
            self::$themeConfig = Utils::getThemeConfig( self::$projectRoot, self::$stubDir );

            self::$features   = self::$themeConfig['features'] ?? [];
            self::$extensions = self::$themeConfig['extensions'] ?? [];
            self::$plugins    = self::$themeConfig['plugins'] ?? [];
        }

        if (
            self::$vendorDir === '' ||
            self::$projectRoot === '' ||
            self::$stubDir === '' ||
            self::$themeConfig === []
        ) {
            $io->write('<error>Cannot load meros dependancies. Aborting.</error>');
            exit(1); 
        }
    }

    /**
     * Runs after composer dump-autoload. Will check installed packages and
     * handle any relevant theme plugin or extension installations.
     *
     * @param  Event $event
     * @return void
     */
    public static function postAutoloadDump( Event $event ): void
    {
        $composer            = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $io                  = $event->getIO();

        self::initialisePaths($composer, $io); // Initialise paths

        $regenerateConfig = false;

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            $packageType = $package->getType();
            $packageName = $package->getName();
            $extra       = $package->getExtra();

            // getInstallPath returns the path where the package is installed
            // This path will be the symlink target for path repositories
            $installPath = $installationManager->getInstallPath($package);

            // Resolve the real path, especially important for symlinked path repositories
            $realInstallPath = realpath($installPath);

            if ($realInstallPath === false) {
                $io->write("<error>Could not determine real path for {$packageName} at {$installPath}. Skipping.</error>");
                continue;
            }

            // Handle Plugins
            if ($packageType === 'wordpress-plugin') {
                $io->write("Handling plugin package: {$packageName} at {$installPath} (realpath: {$realInstallPath})");

                $pluginInfo = PluginInfo::get($realInstallPath);

                if (!$pluginInfo) {
                    $io->write("<error>No main plugin file found in {$realInstallPath}. Skipping {$packageName}</error>");
                    continue;
                }

                $pluginFile       = $pluginInfo['File'] ?? '';
                $pluginsNamespace = self::$themeConfig['plugins_namespace'] ?? 'App\\Plugins';

                if ($pluginFile === '') {
                    $io->write("<error>Cannot determine theme plugin configuration. Skipping {$packageName}</error>");
                    continue;
                }

                $io->write("Main plugin file detected: {$pluginFile}");

                // Plugin class name derived from the real installed directory name
                $pluginClass = str_replace(' ', '', ucwords(str_replace('-', ' ', basename($realInstallPath))));

                // Config file path is relative to the project root
                $configFile = self::$projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR . $pluginClass . '.php';
                $stubPath   = '';

                $merosBlockPlugins = [
                    'meros-carousel',
                    'meros-dynamic-header',
                    'meros-text-animations'
                ];

                foreach ( $merosBlockPlugins as $merosBlockPlugin ) {
                    if ( str_contains( $packageName, $merosBlockPlugin ) ) {
                        $stubPath = self::$stubDir . DIRECTORY_SEPARATOR . 'MMBlock.stub';
                        break;
                    }
                }

                if ( $stubPath === '' ) {
                    $stubPath = self::$stubDir . DIRECTORY_SEPARATOR . 'Plugin.stub';
                }

                if (file_exists($stubPath) && !file_exists($configFile)) {
                    $io->write("Generating plugin config file.");
                    $stub         = file_get_contents( $stubPath );
                    $replacements = [
                        '{{namespace}}' => $pluginsNamespace,
                        '{{class}}'     => $pluginClass
                    ];

                    $rendered = str_replace(array_keys($replacements), array_values($replacements), $stub);

                    if (!is_dir(dirname($configFile))) {
                        mkdir(dirname($configFile), 0755, true);
                    }
                    file_put_contents($configFile, $rendered);
                    $io->write("<info>Generated: {$configFile}</info>");

                    $pluginClass = $pluginsNamespace . '\\' . $pluginClass;

                    self::$plugins[ $pluginClass ] = [
                        'config' => basename($configFile),
                        'src'    => $pluginFile
                    ];

                    $regenerateConfig = true;
                }
            }

            // Handle Extensions
            else if (isset($extra['meros'], $extra['meros']['class'], $extra['meros']['name'])) {
                $io->write("Handling extension package: {$packageName} at {$installPath} (realpath: {$realInstallPath})");

                $extensionsNamespace = self::$themeConfig['extensions_namespace'] ?? 'App\\Extensions;';

                $overrideClass = $extra['meros']['name'];

                if ($extra['meros']['allowOverrides'] ?? true) {
                    // Override file path is relative to the project root
                    $overrideFile = self::$projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Extensions' . DIRECTORY_SEPARATOR . "{$overrideClass}.php";
                    $stubPath     = self::$stubDir . DIRECTORY_SEPARATOR . 'Extension.stub';

                    if (file_exists($stubPath) && !file_exists($overrideFile)) {
                        $stub         = file_get_contents($stubPath);
                        $replacements = [
                            '{{namespace}}' => $extensionsNamespace,
                            '{{extension}}' => $extra['meros']['class'],
                            '{{class}}'     => $overrideClass
                        ];

                        $rendered = str_replace(array_keys($replacements), array_values($replacements), $stub);

                        if (!is_dir(dirname($overrideFile))) {
                            mkdir(dirname($overrideFile), 0755, true);
                        }
                        file_put_contents($overrideFile, $rendered);

                        $io->write("<info>Generated: {$overrideFile}</info>");

                        $overrideClass = $extensionsNamespace . '\\' . $overrideClass;

                        self::$extensions[ $overrideClass ] = basename($overrideFile);

                        $regenerateConfig = true;
                    }
                }
            }

            // Update livewire assets
            else if ($packageName === 'livewire/livewire') {
                self::publishLivewireAssets( $io );
            }
        }

        if ($regenerateConfig) {
            $io->write("<info>Regenerating theme config</info>");

            // Regenerate theme config
            Utils::regenerateThemeConfig(
                self::$stubDir,
                self::$projectRoot,
                self::$themeConfig,
                self::$features,
                self::$extensions,
                self::$plugins
            );
        }
    }

    /**
     * Publishes Livewire assets to the theme's assets directory.
     *
     * @return void
     */
    public static function publishLivewireAssets( $io ): void
    {
        $projectRoot = self::$projectRoot;

        $io->write("Attempting to republish Livewire Assets to {$projectRoot}/assets/livewire");

        $testCommand = "cd {$projectRoot} && wp acorn";
        exec($testCommand, $testOutput, $testStatus);

        if ($testStatus !== 0) {
            $io->write("<error>Meros theme not currently activated or WP CLI unavailable. Skipping publish Livewire assets.</error>");
            return;
        }

        $command = "cd {$projectRoot} && wp acorn livewire:publish --assets";

        exec($command, $output, $status);

        if ($status !== 0) {
            $io->write("<error>Failed to publish Livewire assets. Check that WP CLI is installed in the environment.</error>");
        }

        $source = "{$projectRoot}/public/vendor/livewire";
        $destination = "{$projectRoot}/assets/livewire";

        // Ensure the destination directory is clean
        if (is_dir($destination)) {
            self::deleteDirectory($destination);
        }

        // Move the directory
        if (!rename($source, $destination)) {
            $io->write("<error>Failed to move Livewire assets from {$source} to {$destination}</error>");
        } else {
            $io->write("<info>Successfully published Livewire assets to {$destination}</info>");
        }

        // Delete the vendor directory
        $vendorDir = "{$projectRoot}/public/vendor";
        if (is_dir($vendorDir)) {
            self::deleteDirectory($vendorDir);
        }
    }

    /**
     * Deletes a directory and all its contents recursively.
     *
     * @param  string $dir The directory to delete.
     * @return void
     */
    protected static function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}