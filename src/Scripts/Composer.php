<?php

namespace MM\Meros\Scripts;

use Composer\Composer as ComposerInstance;
use Composer\IO\IOInterface;
use Composer\Script\Event;

class Composer {
    /**
     * Features configuration.
     */
    private static array $featuresConfig;

    /**
     * Environment configuration.
     */
    private static array $environmentConfig;

    /**
     * Features defined in the theme's config.
     */
    private static array $features;

    /**
     * Extensions defined in the theme's config.
     */
    private static array $extensions;

    /**
     * The root path of the WordPress installation.
     */
    private static string $wordpressRoot;

    /**
     * The root path of the Composer project (and assumed theme root).
     */
    private static string $projectRoot;

    /**
     * The path to the 'vendor' directory.
     */
    private static string $vendorDir;

    /**
     * The path to the 'stubs' directory for Meros scripts.
     */
    private static string $stubDir;

    /**
     * Initializes static properties based on the Composer event.
     * This should be called at the beginning of any public static method that
     * needs path information.
     */
    private static function initialise(ComposerInstance $composer, IOInterface $io): void {
        $vendorDir = realpath($composer->getConfig()->get('vendor-dir'));
        $directories = Utils::getDirectories($vendorDir);

        if ($directories !== false) {
            // Set paths
            self::$wordpressRoot = $directories['wordpressRoot'] ?? '';
            self::$vendorDir = $directories['vendorDir'] ?? '';
            self::$projectRoot = $directories['projectRoot'] ?? '';
            self::$stubDir = $directories['stubDir'] ?? '';

            // Set configurations
            self::$featuresConfig = Utils::getConfig('features', self::$projectRoot, self::$stubDir);
            self::$features = self::$featuresConfig['features'] ?? [];
            self::$extensions = self::$featuresConfig['extensions'] ?? [];
            self::$environmentConfig = Utils::getConfig('environments', self::$projectRoot, self::$stubDir);
        }

        if (
            self::$vendorDir === '' ||
            self::$projectRoot === '' ||
            self::$stubDir === '' ||
            self::$featuresConfig === []
        ) {
            $io->write('<error>Cannot load meros dependancies. Aborting.</error>');
            exit(1);
        }
    }

    /**
     * Runs after composer dump-autoload. Will check installed packages and
     * handle any relevant theme plugin or extension installations.
     */
    public static function postAutoloadDump(Event $event): void {
        $composer = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $io = $event->getIO();

        self::initialise($composer, $io);

        $runningInMeros = getenv('MEROS_ENVIRONMENT') === 'true';
        $wordpressInstalled =
            is_dir(self::$wordpressRoot) &&
            is_file(self::$wordpressRoot . DIRECTORY_SEPARATOR . 'wp-config.php');

        if ($runningInMeros && ! $wordpressInstalled) {
            self::configureEnvironment($io);
        }

        $regenerateConfig = false;
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

            // Handle Extensions
            elseif (isset($extra['meros'], $extra['meros']['loader'], $extra['meros']['namespace'])) {
                $installed = self::installExtension($io, $extra['meros']);
                if ($installed) {
                    $regenerateConfig = true;
                }
            }

            // Update livewire assets
            elseif ($packageName === 'livewire/livewire') {
                self::publishLivewireAssets($io);
            }
        }

        if ($regenerateConfig) {
            $io->write('<info>Regenerating theme config</info>');

            // Regenerate theme config
            Utils::regenerateFeaturesConfig(
                self::$stubDir,
                self::$projectRoot,
                self::$featuresConfig,
                self::$features,
                self::$extensions
            );
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
    public static function publishLivewireAssets(IOInterface $io): void {
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
    protected static function configureEnvironment(IOInterface $io): void {
        $io->write('<info>Installing & Configuring WordPress...</info>');
        
        // Load environment variables
        $config = Utils::getLocalEnvironmentConfig(self::$projectRoot);

        // Ensure environment config file exists (for Livewire)
        Utils::makeProjectEnvFile(self::$projectRoot);

        // Ensure we can get the WordPress root
        $wordpressRoot = self::$wordpressRoot !== '' ? self::$wordpressRoot : false;
        if (! $wordpressRoot) {
            $io->write('<error>Cannot determine WordPress root. Aborting.</error>');
            exit(1);
        }

        // Determine theme slug for activation
        $themeSlug = basename(self::$projectRoot);

        // Command for creating wp-config.php
        $configCommand = sprintf(
            'cd %s && wp config create --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --dbcharset=%s --dbcollate=%s --dbprefix=%s',
            escapeshellarg($wordpressRoot),
            escapeshellarg($config['db']['name']),
            escapeshellarg($config['db']['user']),
            escapeshellarg($config['db']['pass']),
            escapeshellarg($config['db']['host']),
            escapeshellarg($config['db']['charset']),
            escapeshellarg($config['db']['collate']),
            escapeshellarg($config['db']['prefix'])
        );

        // Command for installing WordPress
        $installCommand = sprintf(
            'cd %s && wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --skip-email',
            escapeshellarg($wordpressRoot),
            escapeshellarg($config['url']),
            escapeshellarg($config['site_title']),
            escapeshellarg($config['admin']['user']),
            escapeshellarg($config['admin']['password']),
            escapeshellarg($config['admin']['email'])
        );

        // Command for activating the Meros theme
        $activateThemeCommand = sprintf(
            'cd %s && wp theme activate %s',
            escapeshellarg($wordpressRoot),
            escapeshellarg($themeSlug)
        );

        // 1. Wait for DB
        self::waitForMysql(
            $config['db']['host'], 
            $config['db']['user'], 
            $config['db']['pass']
        );

        // 2. Create wp-config.php
        exec($configCommand, $configOutput, $configStatus);
        if ($configStatus !== 0) {
            $io->write('<error>Failed to create wp-config.php.</error>');
            exit(1);
        }

        // 3. Install WordPress
        exec($installCommand, $installOutput, $installStatus);
        if ($installStatus !== 0) {
            $io->write('<error>Failed to install WordPress.</error>');
            exit(1);
        }

        // 4. Configure default options
        $options = self::$environmentConfig['default_options'] ?? [];
        foreach ($options as $optionName => $optionValue) {
            $setOptionCommand = sprintf(
                'cd %s && wp option update %s %s',
                escapeshellarg($wordpressRoot),
                escapeshellarg($optionName),
                escapeshellarg($optionValue)
            );
            exec($setOptionCommand);
        }

        // 5. Activate Theme
        exec($activateThemeCommand, $activateOutput, $activateStatus);
        if ($activateStatus !== 0) {
            $io->write('<error>Failed to activate Meros theme.</error>');
            exit(1);
        }

        $io->write('<info>WordPress installation complete.</info>');
    }

    /**
     * Waits for a MySQL server to become available.
     *
     * @param  string  $host  The MySQL host.
     * @param  string  $user  The MySQL username.
     * @param  string  $pass  The MySQL password.
     * @param  int  $port  The MySQL port.
     * @param  int  $timeoutSeconds  The timeout in seconds.
     *
     * @throws \RuntimeException If the MySQL server does not become available within the timeout.
     */
    private static function waitForMysql(
        string $host,
        string $user,
        string $pass,
        int $port = 3306,
        int $timeoutSeconds = 60
    ): void {
        $start = time();
        $lastError = null;

        do {
            $mysqli = new \mysqli($host, $user, $pass, null, $port);

            if ($mysqli->connect_errno === 0) {
                $mysqli->close();

                return;
            }

            $lastError = $mysqli->connect_error;
            $mysqli->close();

            sleep(1);
        } while (time() - $start < $timeoutSeconds);

        throw new \RuntimeException(
            sprintf(
                'Timed out waiting for MySQL at %s:%d. Last error: %s',
                $host,
                $port,
                $lastError
            )
        );
    }

    /**
     * Installs or registers a Meros extension based on the provided configuration.
     *
     * If overrides are allowed, this method will generate an override class file from a stub,
     * unless it already exists. Otherwise, it registers the extension by its fully qualified class name.
     *
     * @param  IOInterface  $io Composer IO interface for output.
     * @param  array  $config Extension configuration array.
     * 
     * @return boolean True if the extension was installed or registered, false otherwise.
     */
    private static function installExtension(IOInterface $io, array $config): bool {
        $namespace = $config['namespace'];
        $loader = $config['loader'];
        $allowOverrides = $config['allow_overrides'] ?? false;

        if ($allowOverrides === true) {
            $extensionsNamespace = self::$featuresConfig['extensions_namespace'] ?? 'App\\Extensions;';
            $overrideClass = $loader;
            $fqOverrideClass = $extensionsNamespace . '\\' . $overrideClass;

            $overrideFile = self::$projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Extensions' . DIRECTORY_SEPARATOR . "{$overrideClass}.php";
            $stubPath = self::$stubDir . DIRECTORY_SEPARATOR . 'Extension.stub';
            $installed = file_exists($overrideFile) && in_array($fqOverrideClass, self::$extensions, true);

            if ($installed) {
                return false;
            }

            if (file_exists($stubPath) && ! file_exists($overrideFile)) {
                $io->write("<info>Installing extension {$fqOverrideClass}</info>");
                $stub = file_get_contents($stubPath);
                $replacements = [
                    '{{namespace}}' => $extensionsNamespace,
                    '{{extension}}' => $config['class'],
                    '{{class}}' => $overrideClass,
                ];

                $rendered = str_replace(array_keys($replacements), array_values($replacements), $stub);

                if (! is_dir(dirname($overrideFile))) {
                    mkdir(dirname($overrideFile), 0755, true);
                }
                file_put_contents($overrideFile, $rendered);

                $io->write("<info>Generated: {$overrideFile}</info>");

                self::$extensions[] = $overrideClass;
                return true;
            } else {
                $io->write("<error>Could not find stub file at {$stubPath} or override file already exists at {$overrideFile}. Skipping extension installation.</error>");
                return false;
            }
        } else {
            $fullQualifiedClass = $namespace . '\\' . $loader;
            if (in_array($fullQualifiedClass, self::$extensions, true)) {
                return false;
            }
            $io->write("<info>Registering extension {$fullQualifiedClass}</info>");
            self::$extensions[] = $fullQualifiedClass;
            return true;
        }
    }

    /**
     * Deletes a directory and all its contents recursively.
     *
     * @param  string  $dir  The directory to delete.
     */
    protected static function deleteDirectory(string $dir): void {
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
