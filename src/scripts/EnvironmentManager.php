<?php 

namespace MM\Meros\Scripts;

use Dotenv\Dotenv;

class EnvironmentManager {
    /** 
     * The environment name.
     * 
     * @var string
     */
    private string $name;

    /** 
     * The theme path.
     * 
     * @var string
     */
    private string $themePath;

    /** 
     * The theme slug.
     * 
     * @var string
     */
    private string $themeSlug;

    /** 
     * The Meros framework path.
     * 
     * @var string
     */
    private string $frameworkPath;

    /** 
     * The scripts path.
     * 
     * @var string
     */
    private string $scriptsPath;

    /** 
     * The devcontainer path.
     * 
     * @var string
     */
    private string $devContainerPath;

    /** 
     * The devcontainer keys path.
     * 
     * @var string
     */
    private string $keysPath;

    /** 
     * The theme class name.
     * 
     * @var string
     */
    private string $themeClass;

    /** 
     * Whether the environment is local.
     * 
     * @var bool
     */
    private bool $isLocal = false;

    /** 
     * The environment configuration.
     * 
     * @var array
     */
    private array $config = [];

    /** 
     * The available scripts.
     * 
     * @var array
     */
    private array $scripts = [];

    /** 
     * The theme's packages.
     * 
     * @var array
     */
    private array $packages = [];

    /** 
     * The last error message.
     * 
     * @var string
     */
    private string $error = '';

    private function __construct( string $name, string $themePath ) {
        $this->name = $name === 'local' ? 'local_dev' : $name;

        $separator = DIRECTORY_SEPARATOR;
        $this->themePath = realpath( $themePath );
        $this->themeSlug = basename( $this->themePath );
        $this->isLocal   = $this->name === 'local_dev';
        
        $initialised = $this->initFrameworkPath( $separator );
        if ( $initialised !== true ) {
            $this->error = $initialised;
            return;
        }
        
        $initialised = $this->initDevContainerPath( $separator );
        if ( $initialised !== true ) {
            $this->error = $initialised;
            return;
        }

        $initialised = $this->name === 'local_dev' && isset( $_ENV['MEROS_ENVIRONMENT'] )
            ? $this->initLocalConfig( $separator )
            : $this->initRemoteConfig( $separator );

        if ( $initialised !== true ) {
            $this->error = $initialised;
            return;
        }

        $this->initScripts( $separator );
    }

    /**
     * Returns an instance of the EnvironmentManager
     * for the given environment name and theme path.
     * 
     * @param string $name The environment name.
     * @param string|null $themePath The theme path.
     * @return self The EnvironmentManager instance.
     */
    public static function get(string $name, ?string $themePath = null): self {
        if ($themePath === null) {
            $themePath = get_stylesheet_directory();
        }

        $instance = new self($name, $themePath);

        if (!is_dir($themePath)) {
            $instance->error = 'Theme path not found.';
        }

        return $instance;   
    }

    /**
     * Creates a new WordPress installation
     * in the environment's configured path.
     * 
     * @return bool True on success, false on failure.
     */
    public function create(): bool {
        if ($this->error !== '') {
            return false;
        }

        if (! $this->isLocal) {
            $this->error = 'Environment is not local.';
            return false;
        }

        $options = [];
        $configPath = $this->themePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'environments.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $options = $config['local_options'] ?? [];
        }

        $configCommand = sprintf(
        'cd %s && wp config create --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --dbcharset=%s --dbcollate=%s --dbprefix=%s',
            escapeshellarg($this->config['path']),
            escapeshellarg($this->config['db']['name']),
            escapeshellarg($this->config['db']['user']),
            escapeshellarg($this->config['db']['pass']),
            escapeshellarg($this->config['db']['host']),
            escapeshellarg($this->config['db']['charset']),
            escapeshellarg($this->config['db']['collate']),
            escapeshellarg($this->config['db']['prefix'])
        );

        $installCommand = sprintf(
            'cd %s && wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --skip-email',
            escapeshellarg($this->config['path']),
            escapeshellarg($this->config['url']),
            escapeshellarg($this->config['site_title']),
            escapeshellarg($this->config['admin']['user']),
            escapeshellarg($this->config['admin']['password']),
            escapeshellarg($this->config['admin']['email'])
        );

        $activateThemeCommand = sprintf(
            'cd %s && wp theme activate %s',
            escapeshellarg($this->config['path']),
            escapeshellarg($this->themeSlug)
        );

        $this->waitForDBReady(
            $this->config['db']['host'],
            $this->config['db']['user'],
            $this->config['db']['pass'],
            3306,
            60
        );

        exec($configCommand, $configOutput, $configStatus);
        if ($configStatus !== 0) {
            $this->error = 'Failed to create wp-config.php: ' . implode("\n", $configOutput);
            return false;
        }

        exec($installCommand, $installOutput, $installStatus);
        if ($installStatus !== 0) {
            $this->error = 'Failed to install WordPress: ' . implode("\n", $installOutput);
            return false;
        }

        foreach($options as $option => $value) {
            $optionCommand = sprintf(
                'cd %s && wp option update %s %s',
                escapeshellarg($this->config['path']),
                escapeshellarg($option),
                escapeshellarg($value)
            );
            exec($optionCommand);
        }

        $flushCommand = sprintf(
            'cd %s && wp rewrite flush',
            escapeshellarg($this->config['path'])
        );
        exec($flushCommand);

        exec($activateThemeCommand, $themeOutput, $themeStatus);
        if ($themeStatus !== 0) {
            $this->error = 'Failed to activate theme: ' . implode("\n", $themeOutput);
            return false;
        }

        return true;
    }

    /**
     * Installs a package in the environment's theme.
     * 
     * @param string $serviceProvider The package's service provider class.
     * 
     * @return bool  True on success, false on failure.
     */
    public function installPackage(string $serviceProvider): bool {
        if ($this->error !== '') {
            return false;
        }

        if (! $this->isLocal) {
            $this->error = 'Packages can only be installed in a local environment.';
            return false;
        }

        $themeConfigInitialised = $this->initThemeConfig( DIRECTORY_SEPARATOR );
        if (! $themeConfigInitialised ) {
            $this->error = 'Failed to initialise theme configuration.';
            return false;
        }

        if (in_array($serviceProvider, $this->packages)) {
            $this->error = 'Package already installed.';
            return false;
        }

        $this->packages[] = $serviceProvider;
        return $this->regenerateThemeConfig();
    }

    /**
     * Connects to the remote environment via SSH.
     * 
     * @return bool True on success, false on failure.
     */
    public function connect(): bool {
        if ($this->error !== '') {
            return false;
        }

        if ($this->isLocal) {
            $this->error = 'Cannot connect to local environment.';
            return false;
        }

        $script = $this->scripts['connect-env'] ?? '';
        if ($script === '') {
            return 'Connect script not found.';
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= $this->getSSHCommand();

        passthru($command, $return_var);
        if ($return_var !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Syncs the theme from this environment to the specified
     * destination environment.
     * 
     * @param string $destName The destination environment name.

     * @return bool  True on success, false on failure.
     */
    public function syncTheme(string $destName): bool {
        if ($this->error !== '') {
            return false;
        }

        if ($destName === $this->name) {
            $this->error = 'Source and destination environments cannot be the same.';
            return false;
        }

        if ($destName === 'local_dev' || $destName === 'local') {
            $this->error = 'Syncing the theme to local environment is not supported.';
            return false;
        }

        $dest = EnvironmentManager::get($destName, $this->themePath);
        $destConfig = $dest->getConfig();
        
        if (is_string($destConfig)) {
            $this->error = $destConfig;
            return false;
        }

        $script = $this->scripts['sync-theme'] ?? '';
        if ($script === '') {
            $this->error = 'Sync theme script not found.';
            return false;
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= escapeshellarg($this->themeSlug) . ' ';
        $command .= escapeshellarg($destName) . ' ';
        $command .= escapeshellarg($destConfig['url']) . ' ';
        $command .= $dest->getSSHCommand() . ' ';
        $command .= escapeshellarg($this->name) . ' ';
        $command .= escapeshellarg($this->config['url']) . ' ';
        $command .= $this->getSSHCommand();

        passthru($command, $return_var);

        if ($return_var !== 0) {
            $this->error = 'Failed to sync theme from ' . $this->name . ' to ' . $destName . '.';
            return false;
        }

        return true;
    }

    /**
     * Syncs the database from this environment to the specified
     * destination environment.
     * 
     * @param string $destName The destination environment name.
     * @param array  $tables The tables to sync.
     * @param array  $options Additional options for syncing.
     * @param array  $maintainOptions Options to maintain on the destination environment.
     * @param bool   $dropTables Whether to drop tables on the destination before syncing.
     * @param bool   $searchReplace Whether to perform search and replace on URLs in the database.
     * 
     * @return bool  True on success, false on failure.
     */
    public function syncDatabase(
        string $destName, 
        array  $tables, 
        array  $options = [],
        array  $maintainOptions = [],
        bool   $dropTables = false,
        bool   $searchReplace = true
    ): bool {
        if ($this->error !== '') {
            return false;
        }

        $destName = $destName === 'local' ? 'local_dev' : $destName;

        if ($destName === $this->name) {
            $this->error = 'Source and destination environments cannot be the same.';
            return false;
        }

        $dest = EnvironmentManager::get($destName, $this->themePath);
        $destConfig = $dest->getConfig();
        if (is_string($destConfig)) {
            $this->error = $destConfig;
            return false;
        }

        $prefixedTables = [];
        foreach ($tables as $table) {
            $prefixedTables[] = $this->config['db']['prefix'] . $table;
        }

        $script = $this->scripts['sync-db'] ?? '';
        if ($script === '') {
            $this->error = 'Sync database script not found.';
            return false;
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= escapeshellarg($destName) . ' ';
        $command .= escapeshellarg($destConfig['url']) . ' ';
        $command .= $dest->getSSHCommand() . ' ';
        $command .= escapeshellarg($this->name) . ' ';
        $command .= escapeshellarg($this->config['url']) . ' ';
        $command .= $this->getSSHCommand() . ' ';
        $command .= escapeshellarg($this->config['db']['prefix']) . ' ';
        $command .= escapeshellarg($destConfig['db']['prefix']) . ' '; 
        $command .= escapeshellarg($prefixedTables !== [] ? implode(',', $prefixedTables) : '') . ' ';
        $command .= escapeshellarg($options !== [] ? implode(',', $options) : '') . ' ';
        $command .= escapeshellarg($maintainOptions !== [] ? json_encode($maintainOptions) : '') . ' ';
        $command .= escapeshellarg($dropTables ? 'true' : 'false') . ' ';
        $command .= escapeshellarg($searchReplace ? 'true' : 'false');

        passthru($command, $return_var);

        if ($return_var !== 0) {
            $this->error = 'Failed to sync tables from ' . $this->name . ' to ' . $destName . '.';
            return false;
        }

        return true;
    }

    /**
     * Syncs the plugins from this environment to the specified
     * destination environment.
     * 
     * @param string        $destName The destination environment name.
     * @param array|string  $plugins The plugins to sync. If empty or 'all', all plugins will be synced.
     * @param bool          $activate Whether to activate the plugins on the destination environment after syncing.
     * 
     * @return bool True on success, false on failure.
     */
    public function syncPlugins(string $destName, array|string $plugins, bool $activate = true): bool {
        if ($this->error !== '') {
            return false;
        }

        $destName = $destName === 'local' ? 'local_dev' : $destName;

        if ($destName === $this->name) {
            $this->error = 'Source and destination environments cannot be the same.';
            return false;
        }

        if (! is_array($plugins) && $plugins !== 'all') {
            $this->error = 'Plugins must be an array of plugin slugs or "all".';
            return false;
        }

        $dest = EnvironmentManager::get($destName, $this->themePath);
        $destConfig = $dest->getConfig();
        if (is_string($destConfig)) {
            $this->error = $destConfig;
            return false;
        }

        $script = $this->scripts['sync-plugins'] ?? '';
        if ($script === '') {
            $this->error = 'Sync plugins script not found.';
            return false;
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= escapeshellarg($destName) . ' ';
        $command .= escapeshellarg($destConfig['url']) . ' ';
        $command .= $dest->getSSHCommand() . ' ';
        $command .= escapeshellarg(is_array($plugins) ? implode(',', $plugins) : 'all') . ' ';
        $command .= escapeshellarg($this->name) . ' ';
        $command .= escapeshellarg($this->config['url']) . ' ';
        $command .= $this->getSSHCommand() . ' ';
        $command .= escapeshellarg($activate ? 'true' : 'false');

        passthru($command, $return_var);

        if ($return_var !== 0) {
            $this->error = 'Failed to sync plugins from ' . $this->name . ' to ' . $destName . '.';
            return false;
        }

        return true;
    }

    /**
     * Syncs the uploads directory from this environment to the specified
     * destination environment.
     * 
     * @param string $destName The destination environment name.
     * 
     * @return bool True on success, false on failure.
     */
    public function syncUploads(string $destName): bool {
        if ($this->error !== '') {
            return false;
        }

        $destName = $destName === 'local' ? 'local_dev' : $destName;

        if ($destName === $this->name) {
            $this->error = 'Source and destination environments cannot be the same.';
            return false;
        }

        $dest = EnvironmentManager::get($destName, $this->themePath);
        $destConfig = $dest->getConfig();
        if (is_string($destConfig)) {
            $this->error = $destConfig;
            return false;
        }

        $script = $this->scripts['sync-uploads'] ?? '';
        if ($script === '') {
            $this->error = 'Sync uploads script not found.';
            return false;
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= escapeshellarg($destName) . ' ';
        $command .= escapeshellarg($destConfig['url']) . ' ';
        $command .= $dest->getSSHCommand() . ' ';
        $command .= escapeshellarg($this->name) . ' ';
        $command .= escapeshellarg($this->config['url']) . ' ';
        $command .= $this->getSSHCommand();

        passthru($command, $return_var);

        if ($return_var !== 0) {
            $this->error = 'Failed to sync uploads from ' . $this->name . ' to ' . $destName . '.';
            return false;
        }

        return true;
    }

    /**
     * Returns the environment's configuration array
     * or an error message if initialisation failed.
     * 
     * @return string|array The configuration array or error message.
     */
    public function getConfig(): string|array {
        return $this->error !== '' ? $this->error : $this->config;
    }

    /**
     * Returns the last error message.
     * 
     * @return string The error message.
     */
    public function getError(): string {
        return $this->error;
    }

    /**
     * Constructs the SSH command for connecting
     * to the remote environment.
     * 
     * @return string The SSH command.
     */
    public function getSSHCommand(): string {
        $command = escapeshellarg($this->config['path']) . ' ';        
        if (! $this->isLocal ) {
            $command .= escapeshellarg($this->config['ssh']['user'] . '@' . $this->config['ssh']['host']) . ' ';
            $command .= escapeshellarg($this->config['ssh']['port']) . ' ';
            $command .= escapeshellarg($this->config['ssh']['key']);
        } else {
            $command .= escapeshellarg('') . ' ';
            $command .= escapeshellarg('') . ' ';
            $command .= escapeshellarg('');
        }

        return $command;
    }

    /**
     * Initialises the framework path properties.
     * 
     * @param string $separator The directory separator.
     * @return string|bool True on success, error message on failure.
     */
    private function initFrameworkPath(string $separator): string|bool {
        $vendorPath = $this->themePath . $separator . 'vendor' . $separator;
        $frameworkPath = 'mirror-and-mountain' . $separator . 'meros-framework' . $separator . 'src';
        $frameworkPath = $vendorPath . $frameworkPath;

        if (is_dir($frameworkPath)) {
            $this->frameworkPath = realpath($frameworkPath);
            $this->scriptsPath = $this->frameworkPath . $separator . 'scripts' . $separator . 'bin';
            return true;
        } else {
            return 'Meros framework path not found.';
        }
    }

    /**
     * Initialises the devcontainer path properties.
     * 
     * @param string $separator The directory separator.
     * @return string|bool True on success, error message on failure.
     */
    private function initDevContainerPath(string $separator): string|bool {
        $devContainerPath = $this->themePath . $separator . '.devcontainer';

        if (is_dir($devContainerPath)) {
            $this->devContainerPath = realpath($devContainerPath);
            if (is_dir($this->devContainerPath . $separator . 'keys')) {
                $this->keysPath = $this->devContainerPath . $separator . 'keys';
                return true;
            } else {
                return 'Devcontainer keys path not found.';
            }
        } else {
            return 'Devcontainer path not found.';
        }
    }

    /**
     * Initialises the local environment configuration
     * from the devcontainer .env file.
     * 
     * @param string $separator The directory separator.
     * @return string|bool True on success, error message on failure.
     */
    private function initLocalConfig(string $separator): string|bool {
        $containerEnv  = $this->devContainerPath . $separator . '.env';
        $wordpressPath = realpath( dirname( $this->themePath, 3 ) );

        if (! is_dir($wordpressPath) || ! is_file($containerEnv)) {
            return 'Devcontainer .env or WordPress path not found.';
        }

        $dotenv = Dotenv::createImmutable(dirname($containerEnv));
        $dotenv->load();

        $port = $_ENV['WP_PORT'] ?? '8000';
        $url  = 'http://localhost:' . $port;
        $codespaceName = $_ENV['CODESPACE_NAME'] ?? null;

        if ($codespaceName) {
            $url = "https://{$codespaceName}-80.app.github.dev";
        }

        $this->config = [
            'site_title' => $_ENV['SITE_TITLE'] ?? 'Meros WP',
            'url'        => $url,
            'path'       => $wordpressPath,
            'admin'  => [
                'user'     => $_ENV['ADMIN_USER'] ?? 'admin',
                'password' => $_ENV['ADMIN_PASSWORD'] ?? 'password',
                'email'    => $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com'
            ],
            'db' => [
                'name'    => $_ENV['DB_NAME'] ?? 'wordpress',
                'user'    => $_ENV['DB_USER'] ?? 'dbuser',
                'pass'    => $_ENV['DB_PASSWORD'] ?? 'dbpassword',
                'host'    => $_ENV['DB_HOST'] ?? 'db',
                'prefix'  => $_ENV['DB_PREFIX'] ?? 'wp_',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                'collate' => $_ENV['DB_COLLATE'] ?? 'utf8mb4_unicode_ci',
            ],
        ];

        return true;
    }

    /**
     * Initialises the remote environment configuration
     * from the theme's config/environments.php file.
     * 
     * @param string $separator The directory separator.
     * @return string|bool True on success, error message on failure.
     */
    private function initRemoteConfig(string $separator): string|bool {
        $configPath = $this->themePath . $separator . 'config' . $separator . 'environments.php';

        if (file_exists($configPath)) {
            $environments = require $configPath;
            $config       = $environments['remote_environments'][$this->name] ?? [];
        }

        if ($config !== []) {
            $validator = function(string $value, string $type): string {
                if ($type === 'string') {
                    return is_string($value) ? $value : '';
                } else {
                    return is_numeric($value) ? $value : '22';
                }
            };

            $getKey = function(string $name, string $separator): string|bool {
                if ($name === '') {
                    return false;
                }

                $file = $this->keysPath . $separator . $name;
                if (file_exists($file)) {
                    chmod($file, 0600);
                    return $file;
                } 
                
                $file = $this->keysPath . $separator . $this->name . $separator . $name;
                if (file_exists($file)) {
                    chmod($file, 0600);
                    return $file;
                }

                $keyName = strtoupper(str_replace(['-', ' '], '_', $this->name)) . 'SSH_KEY';
                $keyValue = $_ENV[$keyName] ?? '';

                if (is_string($keyValue) && $keyValue !== '') {
                    $decoded = base64_decode($keyValue, true);
                    if ($decoded !== false) {
                        $file = $this->keysPath . $separator . $this->name . $separator . $keyName;
                        file_put_contents($file, $decoded);
                        if ($file !== false) {
                            chmod($file, 0600);
                            return $file;
                        }
                    }
                }

                return false;
            };

            $key = $getKey($validator($config['ssh']['key'] ?? '', 'string'), $separator);
            
            if ($key !== false) {
                $this->config = [
                    'url'  => $validator($config['url'] ?? '', 'string'),
                    'path' => $validator($config['path'] ?? '', 'string'),
                    'ssh' => [
                        'host' => $validator($config['ssh']['host'] ?? '', 'string'),
                        'user' => $validator($config['ssh']['user'] ?? '', 'string'),
                        'port' => $validator($config['ssh']['port'] ?? '', 'numeric'),
                        'key'  => $key,
                    ],
                    'db' => [
                        'name'    => $validator($config['db']['name'] ?? '', 'string'),
                        'user'    => $validator($config['db']['user'] ?? '', 'string'),
                        'pass'    => $validator($config['db']['pass'] ?? '', 'string'),
                        'host'    => $validator($config['db']['host'] ?? '', 'string'),
                        'prefix'  => $validator($config['db']['prefix'] ?? '', 'string'),
                        'charset' => $validator($config['db']['charset'] ?? '', 'string'),
                        'collate' => $validator($config['db']['collate'] ?? '', 'string'),
                    ]
                ];
            } else {
                return 'SSH key file not found.';
            }

            if (!isset(
                $this->config['url'],
                $this->config['path'],
                $this->config['ssh']['user'],
                $this->config['ssh']['host'],
                $this->config['ssh']['port'],
                $this->config['ssh']['key'],
                $this->config['db']['prefix'],
            )) {
                return 'Missing required configuration values.';
            }

            return true;
        }

        return 'Environment configuration not found.';
    }

    /**
     * Initialises the available scripts.
     * 
     * @param string $separator The directory separator.
     * @return void
     */
    private function initScripts(string $separator): void {
        $scripts = ['connect-env', 'sync-db', 'sync-plugins', 'sync-uploads', 'sync-theme'];
        foreach ($scripts as $script) {
            $path = $this->scriptsPath . $separator . $script . '.sh';
            if (file_exists($path)) {
                $this->scripts[$script] = $path;
                if (! is_executable($path)) {
                    chmod($path, 0755);
                }
            }
        }
    }

    /**
     * Initialises the theme's configuration from the config/theme.php file.
     * 
     * @param string $separator The directory separator.
     * @return bool  True on success, false on failure.
     */
    private function initThemeConfig(string $separator): bool {
        $configPath = $this->themePath . $separator . 'config' . $separator . 'theme.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $this->themeClass = $config['theme_class'] ?? 'App\\Theme';
            $this->packages   = $config['packages'] ?? [];
            return true;
        } else {
            $this->themeClass = '';
            $this->packages   = [];
            return false;
        }
    }
 
    /**
     * Regenerates the theme configuration file.
     * 
     * @return bool True on success, false on failure.
     */
    private function regenerateThemeConfig(): bool {
        $stubsPath = $this->frameworkPath . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR;
        $stub      = $stubsPath . 'config' . DIRECTORY_SEPARATOR . 'theme.php.stub';

        if (file_exists($stub)) {
            $formatArray = function(array $array, int $indentLevel = 2): string {
                $indent = str_repeat('    ', $indentLevel);
                $lines = ['['];

                foreach ($array as $item) {
                    $formattedValue = var_export($item, true);
                    $lines[] = "{$indent}{$formattedValue},";
                }

                $lines[] = str_repeat('    ', $indentLevel - 1) . ']';
                return implode("\n", $lines);
            };

            $stubContent = file_get_contents($stub);
            $rendered = str_replace(
                [
                    '{{theme_class}}',
                    '{{packages}}',
                ],
                [
                    var_export($this->themeClass, true),
                    $formatArray($this->packages),
                ],
                $stubContent
            );

            $themeConfigFilePath = $this->themePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'theme.php';
            if (! is_dir(dirname($themeConfigFilePath))) {
                mkdir(dirname($themeConfigFilePath), 0755, true);
            }

            if (file_put_contents($themeConfigFilePath, $rendered) !== false) {
                return true;
            }

            $this->error = 'Failed to write theme configuration file.';
            return false;
        } else {
            $this->error = 'Theme stub file not found.';
            return false;
        }
    }

    /**
     * Waits for the database to be ready for connections.
     * 
     * @param string $host The database host.
     * @param string $user The database user.
     * @param string $pass The database password.
     * @param int $port The database port.
     * @param int $timeoutSeconds The timeout in seconds.
     * @return void
     * @throws \RuntimeException If the database is not ready within the timeout.
     */
    private function waitForDBReady(
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
}