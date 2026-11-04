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
    private static string $stubsDir = '';

    private static function initialise(Composer $composer, IOInterface $io): void {
        self::$vendorRoot = realpath($composer->getConfig()->get('vendor-dir'));
        self::$projectRoot = realpath(dirname(self::$vendorRoot));
        self::$frameworkRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src');
        self::$stubsDir = realpath(self::$frameworkRoot . DIRECTORY_SEPARATOR . 'stubs');

        $runningInMeros = getenv('MEROS_ENVIRONMENT') === 'true';
        if ($runningInMeros) {
            self::$wordpressRoot = realpath(dirname(self::$projectRoot, 3));
        }

        if (!is_dir(self::$vendorRoot) ||
            !is_dir(self::$projectRoot) ||
            !is_dir(self::$frameworkRoot) ||
            !is_dir(self::$stubsDir)) {
            $io->write('<error>Cannot load meros dependencies. Aborting.</error>');
            exit(1);
        }
    }

    /**
     * Handles the post-create-project command for Composer, setting up the devcontainer and theme configuration.
     *
     * @param Event $event
     *
     * @return void
     */
    public static function postCreateProjectCmd(Event $event): void {
        $composer = $event->getComposer();
        $io = $event->getIO();

        $io->write('<info>Welcome to Meros!</info>');
        $customise = $io->askConfirmation('<question>Would you like to customise configuration for this theme\'s devcontainer? [no]: </question>', false);

        if ($customise === true) {
            self::initialise($composer, $io);

            $containerStubsDir = self::$stubsDir . DIRECTORY_SEPARATOR . 'container';
            $envStub = $containerStubsDir . DIRECTORY_SEPARATOR . '.env.stub';
            $devContainerJsonStub = $containerStubsDir . DIRECTORY_SEPARATOR . 'devcontainer.json.stub';

            $themeStubsDir = self::$stubsDir . DIRECTORY_SEPARATOR . 'theme';
            $styleStub = $themeStubsDir . DIRECTORY_SEPARATOR . 'style.css.stub';

            if (!file_exists($envStub) || !file_exists($devContainerJsonStub) || !file_exists($styleStub)) {
                $io->write('<error>Required stub files are missing. Aborting.</error>');
                exit(1);
            }

            $themeSlug = strtolower(str_replace(' ', '-', pathinfo(self::$projectRoot, PATHINFO_BASENAME)));
            $defaultSiteTitle = ucwords(str_replace('-', ' ', $themeSlug));

            $config = [
                'theme_slug'        => $themeSlug,
                'site_title'        => $defaultSiteTitle,
                'admin_user'        => 'admin',
                'admin_password'    => 'password',
                'admin_email'       => 'admin@example.com',
                'db_name'           => 'wordpress',
                'db_host'           => 'db',
                'db_user'           => 'dbuser',
                'db_password'       => 'dbpassword',
                'db_root_password'  => 'rootpassword',
                'db_prefix'         => 'wp_',
                'db_charset'        => 'utf8mb4',
                'db_collate'        => 'utf8mb4_unicode_ci',
                'wp_port'           => '8000',
                'phpmyadmin_port'   => '9000',
                'mailpit_smtp_port' => '1025',
                'mailpit_ui_port'   => '8025',
            ];
        
            $config['site_title'] = $io->ask('<question>Enter the site title for the devcontainer: [' . $defaultSiteTitle . ']: </question>', $defaultSiteTitle);

            $configureAdminUser = $io->askConfirmation('<question>Would you like to configure the devcontainer\'s site\'s admin user? [yes]: </question>', true);
            if ($configureAdminUser) {
                $config['admin_user']     = $io->ask('<question>Enter the admin username: [' . $config['admin_user'] . ']: </question>', $config['admin_user']);
                $config['admin_password'] = $io->ask('<question>Enter the admin password: [' . $config['admin_password'] . ']: </question>', $config['admin_password']);
                $config['admin_email']    = $io->ask('<question>Enter the admin email: [' . $config['admin_email'] . ']: </question>', $config['admin_email']);
            }

            $configureDatabase = $io->askConfirmation('<question>Would you like to configure the devcontainer\'s database settings? [yes]: </question>', true);
            if ($configureDatabase) {
                $config['db_host']          = $io->ask('<question>Enter the database host: [' . $config['db_host'] . ']: </question>', $config['db_host']);
                $config['db_user']          = $io->ask('<question>Enter the database user: [' . $config['db_user'] . ']: </question>', $config['db_user']);
                $config['db_password']      = $io->ask('<question>Enter the database password: [' . $config['db_password'] . ']: </question>', $config['db_password']);
                $config['db_root_password'] = $io->ask('<question>Enter the database root password: [' . $config['db_root_password'] . ']: </question>', $config['db_root_password']);
                $config['db_prefix']        = $io->ask('<question>Enter the database prefix: [' . $config['db_prefix'] . ']: </question>', $config['db_prefix']);
                $config['db_charset']       = $io->ask('<question>Enter the database charset: [' . $config['db_charset'] . ']: </question>', $config['db_charset']);
                $config['db_collate']       = $io->ask('<question>Enter the database collate: [' . $config['db_collate'] . ']: </question>', $config['db_collate']);
            }

            $configurePorts = $io->askConfirmation('<question>Would you like to configure the devcontainer\'s port settings? [yes]: </question>', true);
            if ($configurePorts) {
                $config['wp_port'] = $io->ask('<question>Enter the WordPress port: [' . $config['wp_port'] . ']: </question>', $config['wp_port']);
                $config['phpmyadmin_port'] = $io->ask('<question>Enter the phpMyAdmin port: [' . $config['phpmyadmin_port'] . ']: </question>', $config['phpmyadmin_port']);
                $config['mailpit_ui_port'] = $io->ask('<question>Enter the Mailpit UI port: [' . $config['mailpit_ui_port'] . ']: </question>', $config['mailpit_ui_port']);
                $config['mailpit_smtp_port'] = $io->ask('<question>Enter the Mailpit SMTP port: [' . $config['mailpit_smtp_port'] . ']: </question>', $config['mailpit_smtp_port']);
            }
        
            $io->write('');
            $io->write('<info>Configuration summary:</info>');
            foreach ($config as $key => $value) {
                $io->write(sprintf('  %-18s %s', $key, $value));
            }
            $io->write('');

            if (!$io->askConfirmation('<question>Create the devcontainer files with this configuration? [yes]: </question>', true)) {
                $io->write('<comment>Cancelled. No devcontainer files were created.</comment>');
                return;
            }

            $replacements = [];
            foreach ($config as $key => $value) {
                $replacements['{{' . strtoupper($key) . '}}'] = (string) $value;
            }

            $forwardPorts = array_map('intval', [
                $config['wp_port'],
                $config['phpmyadmin_port'],
                $config['mailpit_smtp_port'],
                $config['mailpit_ui_port'],
            ]);

            $envContents   = file_get_contents($envStub);
            $styleContents = file_get_contents($styleStub);
            $jsonContents  = file_get_contents($devContainerJsonStub);

            if ($envContents === false || $jsonContents === false || $styleContents === false) {
                $io->write('<error>Failed to read stub files. Aborting.</error>');
                exit(1);
            }

            $envContents = strtr($envContents, $replacements);
            $styleContents = strtr($styleContents, $replacements);

            // Values inserted into JSON must be escaped
            $jsonReplacements = array_map(
                fn ($value) => substr(json_encode($value), 1, -1),
                $replacements
            );
            $jsonReplacements['{{FORWARD_PORTS}}'] = json_encode($forwardPorts);
            $jsonContents = strtr($jsonContents, $jsonReplacements);

            $devContainerDir = self::$projectRoot . DIRECTORY_SEPARATOR . '.devcontainer';
            $targets = [
                $devContainerDir . DIRECTORY_SEPARATOR . '.env'                => $envContents,
                $devContainerDir . DIRECTORY_SEPARATOR . 'devcontainer.json'   => $jsonContents,
                self::$projectRoot . DIRECTORY_SEPARATOR . 'style.css'         => $styleContents,
            ];

            foreach ($targets as $path => $contents) {
                if (file_exists($path) && !unlink($path)) {
                    $io->write('<error>Failed to remove existing ' . basename($path) . '. Aborting.</error>');
                    exit(1);
                }
                if (file_put_contents($path, $contents) === false) {
                    $io->write('<error>Failed to write ' . basename($path) . '. Aborting.</error>');
                    exit(1);
                }
            }

            $io->write('<info>Devcontainer configuration created. You can modify it using the .env file located in the .devcontainer directory. Happy coding!</info>');
        } else {
            $io->write('<info>You can modify the theme\'s devcontainer using the .env file located in the .devcontainer directory. Happy coding!</info>');
        }
    }

    /**
     * Handles the post-autoload-dump event for Composer, checking installed packages and handling any relevant theme extension installations.
     *
     * @param Event $event
     *
     * @return void
     */
    public static function postAutoloadDump(Event $event): void {
        $composer = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $io = $event->getIO();

        self::initialise($composer, $io);
        $runningInMeros = getenv('MEROS_ENVIRONMENT') === 'true';

        if ($runningInMeros === false) {
            return;
        }

        $environmentManager = EnvironmentManager::get('local_dev', self::$projectRoot);
        $wordpressInstalled =
            is_dir(self::$wordpressRoot) &&
            is_file(self::$wordpressRoot . DIRECTORY_SEPARATOR . 'wp-config.php');

        // Initialise local environment if WP not installed
        if ($wordpressInstalled === false) {
            self::configureWordpress($io, $environmentManager);
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
     * @param object $environmentManager The environment manager instance.
     * 
     * @return void
     */
    private static function configureWordpress(IOInterface $io, object $environmentManager): void {
        $io->write('<info>Installing & Configuring WordPress...</info>');
        
        $status = $environmentManager->create();
        if (! $status) {
            $io->write('<error>Failed to install and configure WordPress: ' . $environmentManager->getError() . '</error>');
        } else {
            $io->write('<info>WordPress installed and configured successfully.</info>');
        }
    }
}
