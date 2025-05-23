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
    private static array $themeConfig = [];

    /**
     * Features defined in the theme's config.
     *
     * @var array
     */
    private static array $features = [];

    /**
     * Extensions defined in the theme's config.
     *
     * @var array
     */
    private static array $extensions = [];

    /**
     * Plugins defined in the theme's config.
     *
     * @var array
     */
    private static array $plugins = [];

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
     * The path to the 'plugins' directory for wordpress-plugin types.
     *
     * @var string
     */
    private static string $pluginsDir;

    /**
     * The path to the 'stubs' directory for Meros scripts.
     *
     * @var string
     */
    private static string $merosStubsDir;

    /**
     * Initializes static properties based on the Composer event.
     * This should be called at the beginning of any public static method that
     * needs path information.
     *
     * @param ComposerInstance $composer
     * @param IOInterface $io
     * @return void
     */
    private static function initializePaths(ComposerInstance $composer, IOInterface $io): void
    {
        // Get the vendor directory path from Composer's configuration
        self::$vendorDir = realpath($composer->getConfig()->get('vendor-dir'));

        if (self::$vendorDir === false) {
            $io->write('<error>Vendor directory not found or inaccessible.</error>');
            exit(1); 
        }

        // Project root is the parent of the vendor directory
        self::$projectRoot = dirname(self::$vendorDir);

        // Path to the custom 'plugins' directory at theme root
        self::$pluginsDir = self::$projectRoot . DIRECTORY_SEPARATOR . 'plugins';

        // Path to the Meros script stubs (relative to this class file)
        self::$merosStubsDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'stubs');
        if (self::$merosStubsDir === false) {
            $io->write('<error>Meros stubs directory not found or inaccessible.</error>');
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
    public static function installPluginsAndExtensions( Event $event ): void
    {
        $composer            = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $io                  = $event->getIO();

        self::initializePaths($composer, $io); // Initialize paths

        self::checkThemeConfig( $io );

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
                $io->write("<info>Handling plugin package: {$packageName} at {$installPath} (realpath: {$realInstallPath})</info>");

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

                $io->write("<info>Main plugin file detected: {$pluginFile}</info>");
                $io->write("Generating plugin class</info>");

                // Plugin class name derived from the real installed directory name
                $pluginClass = str_replace(' ', '', ucwords(str_replace('-', ' ', basename($realInstallPath))));

                // Config file path is relative to the project root
                $configFile  = self::$projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR . $pluginClass . '.php';
                $stubPath    = self::$merosStubsDir . DIRECTORY_SEPARATOR . 'Plugin.stub';

                if (file_exists($stubPath) && !file_exists($configFile)) {
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
                }
            }

            // Handle Extensions
            else if (isset($extra['meros'], $extra['meros']['class'], $extra['meros']['name'])) {
                $io->write("<info>Handling extension package: {$packageName} at {$installPath} (realpath: {$realInstallPath})</info>");

                $extensionsNamespace = self::$themeConfig['extensions_namespace'] ?? 'App\\Extensions;';

                $overrideClass = $extra['meros']['name'];

                if ($extra['meros']['allowOverrides'] ?? true) {
                    // Override file path is relative to the project root
                    $overrideFile = self::$projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Extensions' . DIRECTORY_SEPARATOR . "{$overrideClass}.php";
                    $stubPath     = self::$merosStubsDir . DIRECTORY_SEPARATOR . 'Extension.stub';

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
                    }
                }
            }
        }
        $io->write("<info>Regenerating theme config</info>");
        self::regenerateThemeConfig();
    }

    /**
     * Makes the create-feature.sh script executable.
     *
     * @return void
     */
    public static function makeCreateScriptExecutable( Event $event ): void
    {
        $io = $event->getIO();
        self::initializePaths($event->getComposer(), $io); // Initialize paths

        // These scripts are assumed to be in the same directory as this Composer.php file
        $scriptPathWindows = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'create-feature.bat');
        $scriptPathUnix    = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'create-feature.sh');

        if (PHP_OS_FAMILY !== 'Windows') {
            if ($scriptPathUnix && file_exists($scriptPathUnix)) {
                chmod($scriptPathUnix, 0755);
                $io->write("<info>Made {$scriptPathUnix} executable.</info>");
            } else {
                $io->write("<error>Unix create-feature script not found or inaccessible.</error>");
            }
        } else {
            if (!$scriptPathWindows || !file_exists($scriptPathWindows)) {
                $io->write("<error>Windows create-feature script not found or inaccessible.</error>");
            }
        }
    }

    /**
     * Creates a new feature in app/Features using the Meros
     * scaffold.
     *
     * @param Event $event
     * @return void
     */
    public static function createFeature( Event $event ): void
    {
        $io = $event->getIO();
        self::initializePaths($event->getComposer(), $io); // Initialize paths

        self::checkThemeConfig($io);

        $io->write("Enter feature name: ");
        $featureName = trim(fgets(STDIN));

        if ($featureName === '') {
            $io->write("<error>Feature name cannot be empty.</error>");
            exit(1);
        }

        $namespace = self::$themeConfig['features_namespace'] ?? 'App\\Features';

        // These scripts are assumed to be in the same directory as this Composer.php file
        $scriptPathWindows = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'create-feature.bat');
        $scriptPathUnix    = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'create-feature.sh');

        if (!$scriptPathWindows && PHP_OS_FAMILY === 'Windows') {
            $io->write("<error>create-feature.bat not found.</error>");
            exit(1);
        }
        if (!$scriptPathUnix && PHP_OS_FAMILY !== 'Windows') {
            $io->write("<error>create-feature.sh not found.</error>");
            exit(1);
        }

        $script = PHP_OS_FAMILY === 'Windows'
            ? 'cmd /c ' . escapeshellarg($scriptPathWindows) . ' ' . escapeshellarg($featureName) . ' ' . escapeshellarg($namespace)
            : 'sh ' . escapeshellarg($scriptPathUnix) . ' ' . escapeshellarg($featureName) . ' ' . escapeshellarg($namespace);

        $io->write("<info>Running script: $script</info>");
        passthru($script);

        self::$features[$namespace . '\\' . $featureName] = $featureName . '.php';
        self::regenerateThemeConfig();
    }

    /**
     * Checks that a theme config exists and creates one if not.
     *
     * @param  IOInterface|null $io
     * @return void
     */
    private static function checkThemeConfig( ?IOInterface $io = null ): void
    {
        // Theme config path is relative to the project root
        $themeConfigPath     = self::$projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'theme.php';
        $themeConfigTemplate = self::$merosStubsDir . DIRECTORY_SEPARATOR . 'theme.template.php';

        // Check if the actual theme config file exists
        if (!file_exists($themeConfigPath)) {
            if ($io !== null) {
                $io->write("<info>Setting up theme config file</info>");
            }

            if (!file_exists($themeConfigTemplate)) {
                if ($io !== null) {
                    $io->write("<error>Unable to locate theme config template at {$themeConfigTemplate}. Aborting</error>");
                }
                return;
            }

            // Ensure the config directory exists
            if (!is_dir(dirname($themeConfigPath))) {
                mkdir(dirname($themeConfigPath), 0755, true);
            }

            $newThemeConfig = copy($themeConfigTemplate, $themeConfigPath);

            if (!$newThemeConfig) {
                if ($io !== null) {
                    $io->write("<error>Unable to create theme config at {$themeConfigPath}. Aborting</error>");
                }
                return;
            }

            if ($io !== null) {
                $io->write("<info>Generated theme config file at {$themeConfigPath}</info>");
            }
        }

        self::$themeConfig = require $themeConfigPath;
    }

    /**
     * Regenerates the theme config file after a feature, extension or
     * plugin is installed.
     *
     * @return void
     */
    private static function regenerateThemeConfig(): void
    {
        $stubPath = self::$merosStubsDir . DIRECTORY_SEPARATOR . 'ThemeConfig.stub';

        if (file_exists($stubPath)) {
            $stub     = file_get_contents( $stubPath );
            $rendered = str_replace(
                [
                    '{{theme_class}}',
                    '{{features_namespace}}',
                    '{{extensions_namespace}}',
                    '{{plugins_namespace}}',
                    '{{features}}',
                    '{{extensions}}',
                    '{{plugins}}'
                ],
                [
                    var_export(self::$themeConfig['theme_class'] ?? 'App\\Theme', true),
                    var_export(self::$themeConfig['features_namespace'] ?? 'App\\Features', true),
                    var_export(self::$themeConfig['extensions_namespace'] ?? 'App\\Extensions', true),
                    var_export(self::$themeConfig['plugins_namespace'] ?? 'App\\Plugins', true),
                    self::formatArray(self::$features, 'features', 2),
                    self::formatArray(self::$extensions, 'extensions', 2),
                    self::formatArray(self::$plugins, 'plugins', 2)
                ],
                $stub
            );

            // Theme config file path relative to project root
            $themeConfigFilePath = self::$projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'theme.php';

            // Ensure the directory exists before writing the file
            if (!is_dir(dirname($themeConfigFilePath))) {
                mkdir(dirname($themeConfigFilePath), 0755, true);
            }

            file_put_contents($themeConfigFilePath, $rendered);
        }
    }

    /**
     * Formats arrays for the theme config file.
     *
     * @param  array       $array
     * @param  string|null $type
     * @param  int         $indentLevel
     * @return string
     */
    private static function formatArray( array $array, ?string $type, int $indentLevel = 2  ): string
    {
        $indent = str_repeat('    ', $indentLevel);
        $lines  = ['['];

        if ( $type !== null ) {
            $array = array_merge($array, self::$themeConfig[ $type ] ?? []);
        }

        foreach ( $array as $key => $value ) {
            $formattedKey   = var_export($key, true);
            $formattedValue = is_array($value)
                ? self::formatArray($value, null, $indentLevel + 1)
                : var_export($value, true);

            $lines[] = "{$indent}{$formattedKey} => {$formattedValue},";
        }

        $lines[] = str_repeat('    ', $indentLevel - 1) . ']';

        return implode("\n", $lines);
    }
}