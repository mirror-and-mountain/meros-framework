<?php

namespace MM\Meros\Scripts;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;

class ComposerScripts {
    private static string $wordpressRoot = '';
    private static string $projectRoot = '';
    private static string $vendorRoot = '';
    private static string $frameworkRoot = '';

    private static function initialise(Composer $composer, IOInterface $io): void {
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

        $runningInMeros     = getenv('MEROS_ENVIRONMENT') === 'true';
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
            $extra       = $package->getExtra();
            $installPath = $installationManager->getInstallPath($package);

            // Resolve the real path
            $realInstallPath = realpath($installPath);

            if ($realInstallPath === false) {
                $io->write("<error>Could not determine real path for {$packageName} at {$installPath}. Skipping.</error>");
                continue;
            }

            // Install package
            elseif (isset($extra['meros'], $extra['meros']['provider'])) {
                $io->write("<info>Installing package {$packageName} from provider {$extra['meros']['provider']}...</info>");

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
                } else {
                    $io->write("<info>Package {$packageName} installed successfully.</info>");
                }
            }
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
}
