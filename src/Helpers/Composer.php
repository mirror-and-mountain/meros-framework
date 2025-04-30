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

            if ($packageType === 'wordpress-plugin') {
                $packageName = $package->getName();
                $installPath = $installationManager->getInstallPath($package);

                $io = $event->getIO();
                $io->write("Handling plugin package: {$packageName} at {$installPath}");

                $pluginInfo = PluginInfo::get( $installPath );

                if (!$pluginInfo) {
                    $io->write("<error>No main plugin file found in {$installPath}</error>");
                    continue;
                }

                $pluginFile = $pluginInfo['File'];
                
                $io->write("<info>Main plugin file detected: {$pluginFile}</info>");
                $io->write("Generating plugin feature class</info>");

                $featureClass = str_replace(' ', '', ucwords(str_replace('-', ' ', basename($installPath))));
                $featureFile  = dirname($installPath, 2) . '/app/Features/' . $featureClass . '.php';
                $stubPath     = dirname(__DIR__) . '/stubs/Feature.stub';

                if (file_exists( $stubPath ) && !file_exists( $featureFile )) {
                    $stub     = file_get_contents( $stubPath );
                    $rendered = str_replace('{{class}}', $featureClass, $stub);

                    file_put_contents($featureFile, $rendered);
                    $io->write("<info>Generated: {$featureFile}</info>");
                }
            }
        }
    }
}
