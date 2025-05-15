<?php

namespace MM\Meros\Helpers;

use MM\Meros\Helpers\PluginInfo;

class Composer
{
    private static array $themeConfig = [];
    private static array $extensions  = [];
    private static array $plugins     = [];

    public static function handleMerosExtensions( $event ): void
    {
        $composer            = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $io                  = $event->getIO();
        $themeConfig         = dirname(__DIR__, 5) . '/config/theme.php';
        $themeConfigTemplate = dirname(__DIR__) . '/config/theme.template.php';
        
        if (!file_exists($themeConfig)) {
            $io->write("<info>Setting up theme config file</info>");
            
            if (!file_exists($themeConfigTemplate)) {
                $io->write("<error>Unable to locate theme config template. Aborting</error>");
                return;
            }

            $newThemeConfig = copy($themeConfigTemplate, $themeConfig);

            if (!$newThemeConfig) {
                $io->write("<error>Unable to create theme config. Aborting</error>");
                return;
            }

            $io->write("<info>Generated theme config file</info>");
        }

        self::$themeConfig = require $themeConfig;

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            $packageType = $package->getType();
            $packageName = $package->getName();
            $extra       = $package->getExtra();
            $installPath = $installationManager->getInstallPath($package);

            // Handle Plugins
            if ($packageType === 'wordpress-plugin') {
                $io->write("<info>Handling plugin package: {$packageName} at {$installPath}</info>");

                $pluginInfo = PluginInfo::get($installPath);

                if (!$pluginInfo) {
                    $io->write("<error>No main plugin file found in {$installPath}. Skipping {$packageName}</error>");
                    continue;
                }

                $pluginFile       = $pluginInfo['File'] ?? '';
                $pluginsNamespace = self::$themeConfig['plugins_namespace'] ?? '';

                if ($pluginFile === '' || $pluginsNamespace === '') {
                    $io->write("<error>Cannot determine theme plugin configuration. Skipping {$packageName}</error>");
                    continue;
                }

                
                $io->write("<info>Main plugin file detected: {$pluginFile}</info>");
                $io->write("Generating plugin class</info>");

                $pluginClass = str_replace(' ', '', ucwords(str_replace('-', ' ', basename($installPath))));
                $configFile  = dirname($installPath, 2) . '/app/Plugins/' . $pluginClass . '.php';
                $stubPath    = dirname(__DIR__) . '/stubs/Plugin.stub';

                if (file_exists($stubPath) && !file_exists($configFile)) {
                    $stub     = file_get_contents( $stubPath );
                    $rendered = str_replace('{{class}}', $pluginClass, $stub);

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
                $io->write("<info>Handling extension package: {$packageName} at {$installPath}</info>");
                
                $extensionsNamespace = self::$themeConfig['extensions_namespace'] ?? '';

                if ($extensionsNamespace === '') {
                    $io->write("<error>Cannot determine theme extension namespace. Skipping {$packageName}</error>");
                    continue;
                }

                $overrideClass = $extra['meros']['name'];

                if ($extra['meros']['allowOverrides'] ?? true) {

                    $overrideFile = dirname($installPath, 3) . "/app/Extensions/{$overrideClass}.php";
                    $stubPath     = dirname(__DIR__) . '/stubs/Extension.stub';

                    if (file_exists($stubPath) && !file_exists($overrideFile)) {
                        $stub         = file_get_contents($stubPath);
                        $replacements = [
                            '{{extension}}' => $extra['meros']['class'],
                            '{{class}}'     => $overrideClass
                        ];

                        $rendered = str_replace(array_keys($replacements), array_values($replacements), $stub);
                        
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

    public static function regenerateThemeConfig(): void
    {
        $stubPath = dirname(__DIR__) . '/stubs/ThemeConfig.stub';

        if ( file_exists( $stubPath ) ) {
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
                    self::formatArray(self::$themeConfig['features'] ?? [], 2, 'features'),
                    self::formatArray(self::$extensions, 2, 'extensions'),
                    self::formatArray(self::$plugins, 2, 'plugins')
                ],
                $stub
            );

            file_put_contents(dirname(__DIR__, 5) . '/config/theme.php', $rendered);
        }
    }

    private static function formatArray( array $array, int $indentLevel = 2, ?string $type ): string
    {
        $indent = str_repeat('    ', $indentLevel);
        $lines  = ['['];

        if ( $type !== null ) {
            $array = array_merge($array, self::$themeConfig[ $type ] ?? []);
        }

        foreach ( $array as $key => $value ) {
            $formattedKey   = var_export($key, true);
            $formattedValue = is_array($value)
                ? self::formatArray($value, $indentLevel + 1, null)
                : var_export($value, true);

            $lines[] = "{$indent}{$formattedKey} => {$formattedValue},";
        }

        $lines[] = str_repeat('    ', $indentLevel - 1) . ']';

        return implode("\n", $lines);
    }
}
