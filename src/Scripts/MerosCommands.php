<?php

namespace MM\Meros\Scripts;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MerosCommands {
    /**
     * The theme slug
     */
    private string $themeSlug = '';

    /**
     * The theme directory
     */
    private string $projectRoot = '';

    /**
     * Meros Framework root directory
     */
    private string $frameworkRoot = '';

    /**
     * The theme's devcontainer keys directory
     */
    private string $keysDir = '';

    /**
     * The theme's vendor directory
     */
    private string $vendorDir = '';

    /**
     * Meros Framework's scripts directory
     */
    private string $scriptsDir = '';

    /**
     * Scripts provided by Meros Framework
     */
    private array $scripts = [];

    /**
     * Meros Framework's stub directory
     */
    private string $stubDir = '';

    /**
     * The theme's features configuration.
     */
    private array $featuresConfig = [];

    /**
     * The theme's registered features as provided by
     * the features config file.
     */
    private array $features = [];

    /**
     * The theme's registered plugins as provided by
     * the features config file.
     */
    private array $extensions = [];

    /**
     * The theme's environments configuration.
     */
    private array $environmentsConfig = [];

    /**
     * The theme's registered environments as provided by
     * the environments config file.
     */
    private array $environments = [];

    /**
     * The local environment configuration
     */
    private array $localEnv = [];

    /**
     * Sets up an instance of this class and sets required properties.
     *
     * @return void
     */
    private function init(): bool {
        $directories = Utils::getDirectories(null);

        if ($directories !== false) {
            // Set directories
            $this->vendorDir     = $directories['vendorDir'] ?? '';
            $this->projectRoot   = $directories['projectRoot'] ?? '';
            $this->frameworkRoot = $directories['frameworkRoot'] ?? '';
            $this->keysDir       = $directories['keysDir'] ?? '';
            $this->scriptsDir    = $directories['scriptsDir'] ?? '';
            $this->stubDir       = $directories['stubDir'] ?? '';

            // Set configurations
            $this->featuresConfig     = Config::get('features', []);
            $this->environmentsConfig = Config::get('environments', []);
            $this->localEnv           = Utils::getLocalEnvironmentConfig($this->projectRoot);

            $this->features     = $this->featuresConfig['features'] ?? [];
            $this->extensions   = $this->featuresConfig['extensions'] ?? [];
            $this->environments = $this->environmentsConfig['remote_envs'] ?? [];

            // Set theme slug
            $this->themeSlug = basename(get_stylesheet_directory());

            // Set scripts
            $scripts = ['create-feature', 'sync-theme', 'sync-plugins', 'sync-media', 'connect-env', 'clone-content'];
            foreach ($scripts as $script) {
                $file = $this->scriptsDir . DIRECTORY_SEPARATOR . 'sh' . DIRECTORY_SEPARATOR . $script . '.sh';
                if (File::exists($file)) {
                    // Set script path
                    $this->scripts[$script] = $file;
                    // Make script executable
                    if (! is_executable($file)) {
                        chmod($file, 0755);
                    }
                }
            }
        } else {
            return false;
        }

        if (
            $this->vendorDir === '' ||
            $this->frameworkRoot === '' ||
            $this->stubDir === '' ||
            $this->keysDir === '' ||
            $this->scriptsDir === '' ||
            $this->projectRoot === '' ||
            $this->featuresConfig === [] ||
            $this->environmentsConfig === [] ||
            $this->localEnv === []
        ) {
            return false;
        }

        return true;
    }

    /**
     * Clones content from one environment to another environment.
     *
     * ## OPTIONS
     *
     * <to>
     * : The environment to clone to.
     * [<from>]
     * : The environment to clone from.
     * 
     * 
     * ## EXAMPLES
     *
     * wp meros clone-content staging
     * wp meros clone-content staging development
     *
     * @subcommand clone-content
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function cloneContent($args) {
        if ($this->init() === false) {
            \WP_CLI::error('Cannot extablish required meros properties. Aborting.');
        }

        // Parse arguments
        $dest    = $args[0] === 'local' ? 'local_dev' : $args[0];
        $source  = $args[1] ?? 'local_dev';

        // Default local environment config
        $localEnv = $this->localEnv;

        // Get destination environment config
        if ($dest !== 'local_dev') {
            $destEnv = $this->validateEnvironment($dest);
            if ($destEnv === false) {
                \WP_CLI::error(sprintf('Destination environment "%s" could not be validated.', $dest));
                return;
            }
        } else {
            $destEnv = $localEnv;
        }

        // Get source environment config if provided
        if ($source !== 'local_dev') {
            $sourceEnv = $this->validateEnvironment($source);
            if ($sourceEnv === false) {
                \WP_CLI::error(sprintf('Source environment "%s" could not be validated.', $source));
                return;
            }
        } else {
            $sourceEnv = $localEnv;
        }
        
        // Get excluded tables settings (source)
        if (isset($sourceEnv['clone_settings']['exclude_tables'])) {
            $excludeTables = $sourceEnv['clone_settings']['exclude_tables'];
        } else {
            $excludeTables = $this->environmentsConfig['clone_settings']['exclude_tables'] ?? [];
        }


        // Prefix tables with database prefix
        $prefixedTables = [];
        foreach ($excludeTables as $table) {
            $prefixedTables[] = $sourceEnv['db']['prefix'] . $table;
        }

        // Prepare script path
        $scriptPath = $this->scripts['clone-env'] ?? '';
        if ($scriptPath === '') {
            \WP_CLI::error('Cannot locate clone-env script. Aborting.');
            return;
        }

        // Execute the script
        \WP_CLI::log(sprintf('Cloning environment from "%s" to "%s"...', $source, $dest));
        $command = 'bash ' . escapeshellarg($scriptPath) . ' ';
        $command .= escapeshellarg($this->themeSlug) . ' ';
        $command .= $this->getSSHCommand($dest, $destEnv) . ' ';
        $command .= $this->getSSHCommand($source, $sourceEnv) . ' ';
        $command .= escapeshellarg($sourceEnv['db']['prefix']) . ' ';
        $command .= escapeshellarg($destEnv['db']['prefix']) . ' ';
        $command .= escapeshellarg(implode(',', $prefixedTables));

        passthru($command, $return_var);

        if ($return_var === 0) {
            \WP_CLI::success(sprintf('Environment clone from "%s" to "%s" completed successfully.', $source, $dest));
        } else {
            \WP_CLI::error(sprintf('Failed to clone environment from "%s" to "%s". Script exited with code %d.', $source, $dest, $return_var));
        }
    }

    /**
     * Creates a new Meros Theme feature.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the feature to create. This will be converted to StudlyCase.
     *
     *
     * ## EXAMPLES
     *
     * wp meros create-feature MyNewFeature
     * wp meros create-feature my-new-feature
     *
     * @subcommand create-feature
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function createFeature($args) {
        if ($this->init() === false) {
            \WP_CLI::error('Cannot extablish required meros properties. Aborting.');
        }

        [$featureName] = $args;

        $formattedName   = Str::studly($featureName);
        $namespacePrefix = $this->featuresConfig['features_namespace'] ?? 'App\\Features'; // Default namespace
        $namespace       = $namespacePrefix . '\\' . $formattedName;

        // Locate the create-feature script
        $scriptPath = $this->scripts['create-feature'] ?? '';
        if ($scriptPath === '') {
            \WP_CLI::error('Cannot locate create-feature script. Aborting.');
            return;
        }

        \WP_CLI::log(sprintf('Attempting to create feature: %s (formatted: %s)', $featureName, $formattedName));
        \WP_CLI::log(sprintf('Using namespace: %s', $namespace));
        \WP_CLI::log(sprintf('Executing script: bash %s %s %s', escapeshellarg($scriptPath), escapeshellarg($formattedName), escapeshellarg($namespace)));

        // Construct the command to execute
        $command = 'bash ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($formattedName) . ' ' . escapeshellarg($namespace);

        passthru($command, $return_var);

        if ($return_var === 0) {
            \WP_CLI::success(sprintf('Feature "%s" created successfully!', $formattedName));
            $this->features[] = $namespace . '\\FeatureDefinition'; // Add newly created feature to the list

            // Regenerate the config file
            Utils::regenerateFeaturesConfig(
                $this->stubDir,
                $this->projectRoot,
                $this->featuresConfig,
                $this->features,
                $this->extensions
            );
        } else {
            \WP_CLI::error(sprintf('Failed to create feature "%s". Script exited with code %d.', $formattedName, $return_var));
        }
    }

    /**
     * Creates an ssh session on a remote environment.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The environment to connect to via SSH.
     *
     * ## EXAMPLES
     *
     * wp meros connect-env production
     *
     * @subcommand connect-env
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function connectToEnvironment($args) {
        // Parse arguments
        $environmentName = $args[0];

        // Get Environment Manager
        $manager = EnvironmentManager::get($environmentName);

        // Connect to environment
        $error = $manager->connect();
        if ($error !== null) {
            \WP_CLI::error($error);
        }
    }

    /**
     * Syncs a theme from and to an environment.
     *
     * ## OPTIONS
     *
     * <to>
     * : The environment to sync the theme to.
     * [<from>]
     * : The environment to sync the theme from.
     *
     * [--activate]
     * : Whether to activate the theme after syncing. (default: true)
     *
     * [--make-dir]
     * : Whether to create the destination directory if it does not exist. (default: true)
     *
     * ## EXAMPLES
     *
     * wp meros sync-theme production
     * wp meros sync-theme staging development
     *
     * @subcommand sync-theme
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function syncTheme($args, $assoc_args) {
        // Parse arguments
        $from = $args[1] ?? 'local_dev';
        $to = $args[0] === 'local' ? 'local_dev' : $args[0];

        // Determine flags
        $makeDir  = isset($assoc_args['make-dir']) && $assoc_args['make-dir'] === false ? false : true;
        $activate = isset($assoc_args['activate']) && $assoc_args['activate'] === false ? false : true;

        // Get Environment Manager
        $manager = EnvironmentManager::get($from);

        // Sync theme
        $status = $manager->syncTheme($to, $makeDir, $activate);
        if ($status !== null) {
            \WP_CLI::error($status);
        } else {
            \WP_CLI::success(sprintf('Theme sync from "%s" to "%s" completed successfully.', $from, $to));
        }
    }

    /**
     * Syncs a site's plugins from and to an environment.
     *
     * ## OPTIONS
     *
     * <to>
     * : The environment to sync the theme to.
     * [<from>]
     * : The environment to sync the theme from.
     *
     * [--activate]
     * : Whether to activate plugins after syncing. (default: true)
     * 
     * [--delete]
     * : Whether to delete plugins in the destination that are not present in the source. (default: false)
     *
     * [--make-dir]
     * : Whether to create the destination directory if it does not exist. (default: true)
     *
     * ## EXAMPLES
     *
     * wp meros sync-plugins production
     * wp meros sync-plugins staging development
     *
     * @subcommand sync-plugins
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function syncPlugins($args, $assoc_args) {
        $this->syncFiles('plugins', $args, $assoc_args);
    }

    /**
     * Syncs a site's plugins from and to an environment.
     *
     * ## OPTIONS
     *
     * <to>
     * : The environment to sync the theme to.
     * [<from>]
     * : The environment to sync the theme from.
     *
     * [--make-dir]
     * : Whether to create the destination directory if it does not exist. (default: true)
     *
     * ## EXAMPLES
     *
     * wp meros sync-media production
     * wp meros sync-media staging development
     *
     * @subcommand sync-media
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function syncMedia($args, $assoc_args) {
        $this->syncFiles('media', $args, $assoc_args);
    }

    /**
     * Syncs files between environments.
     *
     * @param  string  $type  The type of files to sync (theme, plugins, media).
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    private function syncFiles(string $type, array $args, array $assoc_args): void {
        if ($this->init() === false) {
            \WP_CLI::error('Cannot extablish required meros properties. Aborting.');
        }

        // Parse arguments
        $dest    = $args[0] === 'local' ? 'local_dev' : $args[0];
        $source  = $args[1] ?? 'local_dev';
        $makeDir = isset($assoc_args['make-dir']) && $assoc_args['make-dir'] === false ? false : true;

        if ($type !== 'media') {
            $activate = isset($assoc_args['activate']) && $assoc_args['activate'] === false ? false : true;
        }

        if ($type !== 'theme') {
            $delete = isset($assoc_args['delete']) && $assoc_args['delete'] === true ? true : false;
        }

        if ($source === $dest) {
            \WP_CLI::error('Source and destination environments cannot be the same.');

            return;
        }

        if ($dest === 'local_dev' && $type === 'theme') {
            \WP_CLI::error('Destination environment cannot be local_dev for theme syncing.');

            return;
        }

        // Default local environment config
        $localEnv = $this->localEnv;

        // Get destination environment config
        if ($dest !== 'local_dev') {
            $destEnv = $this->validateEnvironment($dest);
            if ($destEnv === false) {
                \WP_CLI::error(sprintf('Destination environment "%s" could not be validated.', $dest));
                return;
            }
        } else {
            $destEnv = $localEnv;
        }

        // Get source environment config if provided
        if ($source !== 'local_dev') {
            $sourceEnv = $this->validateEnvironment($source);
            if ($sourceEnv === false) {
                \WP_CLI::error(sprintf('Source environment "%s" could not be validated.', $source));
                return;
            }
        } else {
            $sourceEnv = $localEnv;
        }

        // Prepare script path
        $scriptPath = $this->scripts["sync-{$type}"] ?? '';
        if ($scriptPath === '') {
            \WP_CLI::error(sprintf('Cannot locate sync-%s script. Aborting.', $type));
            return;
        }

        // Execute the script
        \WP_CLI::log(sprintf('Syncing %s from "%s" to "%s"...', $type, $source, $dest));
        $command = 'bash ' . escapeshellarg($scriptPath) . ' ';
        $command .= escapeshellarg($this->themeSlug) . ' ';
        $command .= $this->getSSHCommand($dest, $destEnv) . ' ';
        $command .= $this->getSSHCommand($source, $sourceEnv) . ' ';
        $command .= escapeshellarg($makeDir ? 'true' : 'false') . ' ';

        if ($type === 'plugins') {
            $command .= escapeshellarg($delete ? 'true' : 'false') . ' ';
        }

        if ($type !== 'media') {
            $command .= escapeshellarg($activate ? 'true' : 'false') . ' ';
        }

        passthru($command, $return_var);

        if ($return_var === 0) {
            \WP_CLI::success(sprintf('%s sync from "%s" to "%s" completed successfully.', ucfirst($type), $source, $dest));
        } else {
            \WP_CLI::error(sprintf('Failed to sync %s from "%s" to "%s". Script exited with code %d.', $type, $source, $dest, $return_var));
        }
    }

    /**
     * Retrieves the configuration for a given environment.
     *
     * @param  string  $name  The name of the environment.
     * @return array|bool The environment configuration if found,
     *                    otherwise false.
     */
    private function getEnvironment(string $name): array|bool {
        return isset($this->environments[$name])
            ? $this->environments[$name]
            : false;
    }

    /**
     * Validates the structure of an environment configuration.
     *
     * @param  string  $name  The name of the environment to validate.
     * @return bool|array Environment array if valid,
     *                    False otherwise.
     */
    private function validateEnvironment(string $name): bool|array {
        $environment = $this->getEnvironment($name);

        if (! $environment) {
            \WP_CLI::error(sprintf('Environment "%s" not found in environments configuration.', $name));

            return false;
        }

        $requiredKeys = [
            'url' => '',
            'path' => '',
            'ssh' => [
                'host',
                'port',
                'user',
                'key',
            ],
            'db' => [
                'name',
                'prefix',
            ],
        ];

        foreach ($requiredKeys as $key => $subkeys) {
            switch ($key) {
                case 'ssh':
                case 'db':
                    // Validate subkey for arrays
                    if (! isset($environment[$key]) || ! is_array($environment[$key])) {
                        \WP_CLI::error(sprintf('Environment "%s" is missing required key "%s" or it is not an array.', $name, $key));

                        return false;
                    }
                    foreach ($subkeys as $subkey) {
                        // Check if subkey exists and is a string
                        if (
                            ! isset($environment[$key][$subkey]) ||
                            ! is_string($environment[$key][$subkey])
                        ) {
                            \WP_CLI::error(sprintf('Environment "%s" is missing required key "%s.%s" or it is not a string.', $name, $key, $subkey));

                            return false;
                        }
                        // Check port is numeric
                        if ($subkey === 'port') {
                            if (! is_numeric($environment[$key][$subkey])) {
                                \WP_CLI::error(sprintf('Environment "%s" has non-numeric port in key "%s.%s".', $name, $key, $subkey));

                                return false;
                            }
                        }
                        // Check that SSH key file exists or try to retrieve it
                        if ($subkey === 'key') {
                            $filePath = $this->keysDir . DIRECTORY_SEPARATOR . $environment[$key][$subkey];
                            if (File::exists($filePath)) {
                                // Update the key path to the retrieved key file
                                $environment[$key][$subkey] = $filePath;

                                continue;
                            }

                            // Try to retrieve from environment variable
                            $keyName = Str::upper(Str::replace(['-', ' '], '_', $name)) . '_SSH_KEY';
                            $keyValue = getenv($keyName);

                            if (is_string($keyValue)) {
                                $filePath = $this->keysDir . DIRECTORY_SEPARATOR . $keyName;
                                if (File::exists($filePath)) {
                                    // Update the key path to the retrieved key file
                                    $environment[$key][$subkey] = $filePath;

                                    continue;
                                } else {
                                    $fileName = $this->makeEnvironmentKeyFile($name, $keyName, $keyValue);
                                    if ($fileName === '') {
                                        \WP_CLI::error(sprintf('Environment "%s" SSH key file "%s" does not exist and could not be retrieved from environment variable.', $name, $environment[$key][$subkey]));

                                        return false;
                                    } else {
                                        // Update the key path to the retrieved key file
                                        $environment[$key][$subkey] = $fileName;
                                    }
                                }
                            } else {
                                \WP_CLI::error(sprintf('Environment "%s" SSH key file "%s" does not exist and no environment variable found to retrieve it.', $name, $environment[$key][$subkey]));

                                return false;
                            }
                        }
                    }
                    break;
                default:
                    // Validate top-level keys
                    if (
                        ! isset($environment[$key]) ||
                        ! is_string($environment[$key])
                    ) {
                        \WP_CLI::error(sprintf('Environment "%s" is missing required key "%s" or it is not a string.', $name, $key));

                        return false;
                    }
                    break;
            }
        }

        return $environment;
    }

    /**
     * Attempts to write the SSH key file for a given environment.
     *
     * @param  string  $environmentName  The name of the environment.
     * @param  string  $keyName  The name of the SSH key.
     * @param  string  $keyValue  The base64-encoded SSH key value.
     * @return string The path to the SSH key file.
     */
    private function makeEnvironmentKeyFile(
        string $environmentName,
        string $keyName,
        string $keyValue
    ): string {
        $decoded = base64_decode($keyValue, true);

        if ($decoded === false) {
            return '';
        }

        $fileName = $this->keysDir . DIRECTORY_SEPARATOR . $keyName;
        $file = file_put_contents($fileName, $decoded);

        if ($file === false) {
            \WP_CLI::error(sprintf('Failed to write SSH key file for environment "%s".', $environmentName));

            return '';
        }
        chmod($fileName, 0600);

        return $fileName;
    }

    /**
     * Constructs an SSH command string for a given environment.
     *
     * @param  string  $environmentName  The name of the environment.
     * @param  array  $environmentConfig  The environment configuration.
     * @return string The constructed SSH command string.
     */
    private function getSSHCommand(string $environmentName, array $environmentConfig): string {
        $command = '';
        $command .= escapeshellarg($environmentName) . ' ';
        $command .= escapeshellarg($environmentConfig['url']) . ' ';
        $command .= escapeshellarg($environmentConfig['path']) . ' ';
        
        if ($environmentName !== 'local_dev') {
            $command .= escapeshellarg($environmentConfig['ssh']['user'] . '@' . $environmentConfig['ssh']['host']) . ' ';
            $command .= escapeshellarg($environmentConfig['ssh']['port']) . ' ';
            $command .= escapeshellarg($environmentConfig['ssh']['key']);
        } else {
            $command .= escapeshellarg('') . ' ';
            $command .= escapeshellarg('') . ' ';
            $command .= escapeshellarg('');
        }

        return $command;
    }
}
