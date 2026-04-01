<?php

namespace MM\Meros\Scripts;

use Composer\Composer as ComposerInstance;
use Composer\IO\IOInterface;
use Composer\Script\Event;

class Composer {
    private static string $wordpressRoot = '';
    private static string $projectRoot = '';
    private static string $vendorRoot = '';
    private static string $frameworkRoot = '';

    private static function initialise(ComposerInstance $composer, IOInterface $io): void {
        self::$vendorRoot = realpath($composer->getConfig()->get('vendor-dir'));
        self::$projectRoot = realpath(dirname(self::$vendorRoot));
        self::$frameworkRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src');

        $runningInMeros = getenv('MEROS_ENVIRONMENT') === 'true';
        if ($runningInMeros) {
            self::$wordpressRoot = realpath(dirname(self::$projectRoot, 3));
        }

        if (!is_dir(self::$vendorRoot) ||
            !is_dir(self::$projectRoot) ||
            !is_dir(self::$frameworkRoot)) {
            $io->write('<error>Cannot load meros dependencies. Aborting.</error>');
            exit(1);
        }
    }

    /**
     * Runs after composer dump-autoload. Will check installed packages and
     * handle any relevant theme extension installations.
     */
    public static function postAutoloadDump(Event $event): void {
        $composer = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $io = $event->getIO();

        self::initialise($composer, $io);
        $environmentManager = EnvironmentManager::get('local_dev', self::$projectRoot);

        $runningInMeros = getenv('MEROS_ENVIRONMENT') === 'true';
        $wordpressInstalled =
            is_dir(self::$wordpressRoot) &&
            is_file(self::$wordpressRoot . DIRECTORY_SEPARATOR . 'wp-config.php');

        // Initialise local environment if WP not installed
        if ($runningInMeros && ! $wordpressInstalled) {
            self::configureEnvironment($io, $environmentManager);
        }

        // Handle package installations
        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            $packageName = $package->getName();
            $extra = $package->getExtra();
            $installPath = $installationManager->getInstallPath($package);

            // Resolve the real path
            $realInstallPath = realpath($installPath);

            if ($realInstallPath === false) {
                $io->write("<error>Could not determine real path for {$packageName} at {$installPath}. Skipping.</error>");
                continue;
            }

            // Install package
            elseif (isset($extra['meros'], $extra['meros']['provider'])) {
                $result = $environmentManager->installPackage(
                    $extra['meros']['provider']
                );

                if ($result !== true) {
                    $error = $environmentManager->getError();
                    if ($error === 'Package already installed.') {
                        $io->write("<info>Package {$packageName} is already installed. Skipping.</info>");
                    } else {
                        $io->write("<error>Failed to install package {$packageName}: {$error}</error>");
                    }
                }
            }

            // Update livewire assets
            elseif ($packageName === 'livewire/livewire') {
                self::publishLivewireAssets($io);
            }
        }
    }

    /**
     * Publishes Livewire assets to the theme's assets directory.
     *
     * Runs WP CLI commands to publish Livewire assets, moves them to the appropriate
     * theme directory, and cleans up the vendor directory. Skips if WP CLI or the theme
     * is not activated.
     *
     * @param IOInterface $io Composer IO interface for output.
     * @return void
     */
    private static function publishLivewireAssets(IOInterface $io): void {
        $projectRoot = self::$projectRoot;

        $io->write("<info>Updating Livewire Assets in the theme directory: {$projectRoot}/assets/livewire</info>");

        $testCommand = "cd {$projectRoot} && wp acorn";
        exec($testCommand, $testOutput, $testStatus);

        if ($testStatus !== 0) {
            $io->write('Meros theme not currently activated or WP CLI unavailable. Skipping publish Livewire assets.');
            return;
        }

        $command = "cd {$projectRoot} && wp acorn livewire:publish --assets";

        exec($command, $output, $status);

        if ($status !== 0) {
            $io->write('<error>Failed to publish Livewire assets. Check that WP CLI is installed in the environment.</error>');
        }

        $source = "{$projectRoot}/public/vendor/livewire";
        $destination = "{$projectRoot}/assets/livewire";

        // Ensure the destination directory is clean
        if (is_dir($destination)) {
            self::deleteDirectory($destination);
        }

        // Move the directory
        if (! rename($source, $destination)) {
            $io->write("<error>Failed to move Livewire assets from {$source} to {$destination}</error>");
        }

        // Delete the vendor directory
        $vendorDir = "{$projectRoot}/public/vendor";
        if (is_dir($vendorDir)) {
            self::deleteDirectory($vendorDir);
        }
    }

    /**
     * Installs and configures WordPress in the specified environment.
     *
     * Sets up the Environments configuration file, installs WordPress,
     * and activates the Meros theme.
     *
     * @param IOInterface $io Composer IO interface for output.
     * @return void
     */
    private static function configureEnvironment(IOInterface $io, object $environmentManager): void {
        $io->write('<info>Installing & Configuring WordPress...</info>');
        
        $status = $environmentManager->create();
        if (! $status) {
            $io->write('<error>Failed to install and configure WordPress: ' . $environmentManager->getError() . '</error>');
        } else {
            $io->write('<info>WordPress installed and configured successfully.</info>');
        }
    }

    /**
     * Deletes a directory and all its contents recursively.
     *
     * @param  string  $dir  The directory to delete.
     */
    private static function deleteDirectory(string $dir): void {
        if (! file_exists($dir)) {
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
