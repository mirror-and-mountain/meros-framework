<?php

namespace MM\Meros\Helpers;

use MM\Meros\Helpers\PluginInfo;

class Composer
{
    public static function afterPluginInstall( $event ): void
    {
        $composer = $event->getComposer();
        $installationManager = $composer->getInstallationManager();

        // Example if you want to loop installed packages:
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
                $pluginFile  = dirname($installPath, 2) . '/app/Plugins/' . $pluginClass . '.php';
                $stubPath    = dirname(__DIR__) . '/stubs/Plugin.stub';

                if (file_exists( $stubPath ) && !file_exists( $pluginFile )) {
                    $stub     = file_get_contents( $stubPath );
                    $rendered = str_replace('{{class}}', $pluginClass, $stub);

                    file_put_contents($pluginFile, $rendered);
                    $io->write("<info>Generated: {$pluginFile}</info>");
                }
            }
            else if (isset($extra['meros'], $extra['meros']['name'])) {
                $io->write("<info>Handling extension package: {$packageName} at {$installPath}</info>");
                $extensionName = $extra['meros']['name'];

                if ($extra['meros']['allowOverrides'] ?? true) {

                    $overrideFile = dirname($installPath, 3) . "/app/Extensions/{$extensionName}.php";
                    $stubPath     = $installPath . '/src/Override.stub';

                    if (file_exists($stubPath) && !file_exists($overrideFile)) {
                        $content = file_get_contents( $stubPath );
                        file_put_contents($overrideFile, $content);

                        $io->write("<info>Generated: {$overrideFile}</info>");
                    }
                }
            }
        }
    }
}
