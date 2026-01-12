<?php 

namespace MM\Meros\Scripts;

use Dotenv\Dotenv;

use function PHPSTORM_META\map;

class EnvironmentManager {
    private string $name;
    private string $themePath;
    private string $themeSlug;
    private string $frameworkPath;
    private string $scriptsPath;
    private string $devContainerPath;
    private string $keysPath;
    private bool   $isLocal = false;
    private array  $config = [];
    private array  $scripts = [];
    private string $error = '';

    private function __construct( string $name, string $themePath ) {
        $this->name = $name === 'local' ? 'local_dev' : $name;

        $separator = DIRECTORY_SEPARATOR;
        $this->themePath = realpath( $themePath );
        $this->themeSlug = basename( $this->themePath );
        $this->isLocal = $this->name === 'local_dev';
        
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

    public function connect(): ?string {
        if ($this->error !== '') {
            return $this->error;
        }

        if ($this->isLocal) {
            return 'Cannot connect to local environment.';
        }

        $script = $this->scripts['connect-env'] ?? '';
        if ($script === '') {
            return 'Connect script not found.';
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= $this->getSSHCommand();

        passthru($command, $return_var);
        if ($return_var !== 0) {
            return 'Failed to connect to remote environment.';
        }

        return null;
    }

    public function syncTheme(string $destName, bool $makeDir = true, bool $activate = true): string {
        if ($this->error !== '') {
            return $this->error;
        }

        if ($destName === $this->name) {
            return 'Source and destination environments cannot be the same.';
        }

        if ($destName === 'local_dev') {
            return 'Syncing the theme to local environment is not supported.';
        }

        $dest = EnvironmentManager::get($destName, $this->themePath);
        $destConfig = $dest->getConfig();
        
        if (is_string($destConfig)) {
            return $destConfig;
        }

        $script = $this->scripts['sync-theme'] ?? '';
        if ($script === '') {
            return 'Sync theme script not found.';
        }

        $command = 'bash ' . escapeshellarg($script) . ' ' ;
        $command .= escapeshellarg($this->themeSlug) . ' ';
        $command .= escapeshellarg($destName) . ' ';
        $command .= escapeshellarg($destConfig['url']) . ' ';
        $command .= $dest->getSSHCommand() . ' ';
        $command .= escapeshellarg($this->name) . ' ';
        $command .= escapeshellarg($this->config['url']) . ' ';
        $command .= $this->getSSHCommand() . ' ';
        $command .= escapeshellarg($makeDir ? 'true' : 'false') . ' ';
        $command .= escapeshellarg($activate ? 'true' : 'false');

        passthru($command, $return_var);

        if ($return_var !== 0) {
            return 'Failed to sync theme from ' . $this->name . ' to ' . $destName . '.';
        }

        return 'Successfully synced theme from ' . $this->name . ' to ' . $destName . '.';
    }

    public function getConfig(): string|array {
        return $this->error !== '' ? $this->error : $this->config;
    }

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

    private function initFrameworkPath(string $separator): string|bool {
        $vendorPath = $this->themePath . $separator . 'vendor' . $separator;
        $frameworkPath = 'mirror-and-mountain' . $separator . 'meros-framework' . $separator . 'src';
        $frameworkPath = $vendorPath . $frameworkPath;

        if (is_dir($frameworkPath)) {
            $this->frameworkPath = realpath($frameworkPath);
            $this->scriptsPath = $this->frameworkPath . $separator . 'Scripts' . $separator . 'sh';
            return true;
        } else {
            return 'Meros framework path not found.';
        }
    }

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

    private function initLocalConfig(string $separator): string|bool {
        $containerEnv = $this->devContainerPath . $separator . '.env';
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
            'url'  => $url,
            'path' => $wordpressPath,
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

    private function initRemoteConfig(string $separator): string|bool {
        $configPath = $this->themePath . $separator . 'config' . $separator . 'environments.php';
        if (file_exists($configPath)) {
            $environments = require $configPath;
            $config = $environments['remote_environments'][$this->name] ?? [];
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
                    return $file;
                } 
                
                $file = $this->keysPath . $separator . $this->name . $separator . $name;
                if (file_exists($file)) {
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

    private function initScripts(string $separator): void {
        $scripts = ['create-feature', 'connect-env', 'clone-content', 'sync-theme', 'sync-plugins'];
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
}