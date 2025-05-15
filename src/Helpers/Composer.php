<?php

namespace MM\Meros\Helpers;

use MM\Meros\Helpers\PluginInfo;

class Composer
{
    public static function handleMerosExtensions( $event ): void
    {
        $composer            = $event->getComposer();
        $installationManager = $composer->getInstallationManager();

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            $packageType = $package->getType();
            $packageName = $package->getName();
            $extra       = $package->getExtra();
            $installPath = $installationManager->getInstallPath($package);
            $io          = $event->getIO();

            if ($packageType === 'wordpress-plugin') {
                $io->write("<info>Handling plugin package: {$packageName} at {$installPath}</info>");

                $pluginInfo = PluginInfo::get( $installPath );

                if (!$pluginInfo) {
                    $io->write("<error>No main plugin file found in {$installPath}</error>");
                    continue;
                }

                $pluginFile = $pluginInfo['File'];
                
                $io->write("<info>Main plugin file detected: {$pluginFile}</info>");
                $io->write("Generating plugin feature class</info>");

                $pluginClass = str_replace(' ', '', ucwords(str_replace('-', ' ', basename($installPath))));
                $configFile  = dirname($installPath, 2) . '/app/Plugins/' . $pluginClass . '.php';
                $stubPath    = dirname(__DIR__) . '/stubs/Plugin.stub';

                if (file_exists( $stubPath ) && !file_exists( $configFile )) {
                    $stub     = file_get_contents( $stubPath );
                    $rendered = str_replace('{{class}}', $pluginClass, $stub);

                    file_put_contents($configFile, $rendered);
                    $io->write("<info>Generated: {$configFile}</info>");
                    $io->write("<info>Updating Theme Config</info>");

                    self::updateThemeConfig( 
                        'plugins', $pluginClass, ['config' => basename($configFile), 'src' => $pluginFile ] 
                    );
                }
            }
            else if (isset($extra['meros'], $extra['meros']['class'], $extra['meros']['name'])) {
                $io->write("<info>Handling extension package: {$packageName} at {$installPath}</info>");
                $extensionName = $extra['meros']['name'];

                if ($extra['meros']['allowOverrides'] ?? true) {

                    $overrideFile = dirname($installPath, 3) . "/app/Extensions/{$extensionName}.php";
                    $stubPath     = dirname(__DIR__) . '/stubs/Extension.stub';

                    if (file_exists($stubPath) && !file_exists($overrideFile)) {
                        $stub         = file_get_contents($stubPath);
                        $replacements = [
                            '{{extension}}' => $extra['meros']['class'],
                            '{{class}}'     => $extensionName
                        ];
                        $rendered = str_replace(array_keys($replacements), array_values($replacements), $stub);
                        
                        file_put_contents($overrideFile, $rendered);
                        $io->write("<info>Generated: {$overrideFile}</info>");
                        $io->write("<info>Updating Theme Config</info>");

                        self::updateThemeConfig( 
                            'extensions', $extensionName, basename($overrideFile)
                        );
                    }
                }
            }
        }
    }
    private static function updateThemeConfig( string $type, string $class, string|array $files ): void
    {
        $themeConfigPath = dirname(__DIR__,5) . '/config/theme.php';

        if (!file_exists($themeConfigPath) || !is_file($themeConfigPath)) {
            return;
        }

        $config = include $themeConfigPath;
        
        if (!isset($config[ $type ]) || isset($config[ $type ][ $class ])) {
            return;
        }

        $config[ $type ][ $class ] = $files;

        $exported = self::exportFormattedConfig($config);
        $output   = "<?php\n\nreturn " . $exported . ";\n";

        file_put_contents($themeConfigPath, $output);

    }
    private static function exportFormattedConfig(array $array, int $indentLevel = 0): string 
    {
        $indent = str_repeat('    ', $indentLevel);
        $nextIndent = str_repeat('    ', $indentLevel + 1);
        $lines = [];

        $lines[] = '[';

        foreach ($array as $key => $value) {
            $formattedKey = is_int($key) ? $key : var_export($key, true);
            
            if (is_array($value)) {
                $formattedValue = self::exportFormattedConfig($value, $indentLevel + 1);
            } else {
                $formattedValue = var_export($value, true);
            }

            $lines[] = "{$nextIndent}{$formattedKey} => {$formattedValue},";
        }

        $lines[] = "{$indent}]";

        return implode("\n", $lines);
    }
}
