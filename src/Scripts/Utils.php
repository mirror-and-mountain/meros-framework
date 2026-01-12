<?php

namespace MM\Meros\Scripts;

use Dotenv\Dotenv;

class Utils {
    public static function createEnvironment(string $envName, string $projectRoot, string $stubDir) {        
        if ($envName === 'local') {
            $config = self::getLocalEnvironmentConfig($projectRoot);
        } else {
            $config = self::getConfig('environments', $projectRoot, $stubDir);
            if (isset($config['remote_envs'][$envName])) {
                $config = $config['remote_envs'][$envName];
            } else {
                return null;
            }
        }
    }

    public static function getLocalEnvironmentConfig(string $projectRoot): array {
        $localEnvPath = $projectRoot . DIRECTORY_SEPARATOR . '.devcontainer' . DIRECTORY_SEPARATOR . '.env';

        // Load environment variables from .env file if it exists (inside .devcontainer)
        if (file_exists($localEnvPath)) {
            $dotenv = Dotenv::createImmutable(dirname($localEnvPath));
            $dotenv->load();
        }

        // Determine URL for Codespaces or default to localhost
        $port = $_ENV['WP_PORT'] ?? '8000';
        $url  = 'http://localhost:' . $port;
        if (isset($_ENV['CODESPACE_NAME'])) {
            $codespaceName = $_ENV['CODESPACE_NAME'];
            $url = "https://{$codespaceName}-80.app.github.dev";
        }

        return [
            'site_title' => $_ENV['SITE_TITLE'] ?? 'Meros WP',
            'url'  => $url,
            'path' => realpath(dirname($projectRoot, 3)),
            'admin'  => [
                'user'     => $_ENV['ADMIN_USER'] ?? 'admin',
                'password' => $_ENV['ADMIN_PASSWORD'] ?? 'password',
                'email'    => $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com',
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
    }

    public static function makeProjectEnvFile(string $projectRoot): void {
        $projectEnvPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';

        // Create an empty .env file if it doesn't exist
        if (! file_exists($projectEnvPath)) {
            file_put_contents($projectEnvPath, '');
        }

        // Get .env content
        $envContent = file_get_contents($projectEnvPath);

        // Bail if file can't be read
        if ($envContent === false) {
            return;
        }

        // Check if APP_KEY is already set or create a new one
        if (! preg_match('/^APP_KEY=.*$/m', $envContent)) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $comment = '# App key required for Livewire functionality';
            $envContent = rtrim($envContent)."{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($projectEnvPath, $envContent);
        }

        return;
    }

    public static function getDirectories(?string $vendorDir): array|bool {
        $wordpressRoot = '';
        $projectRoot = '';
        $frameworkRoot = '';

        if (! isset($vendorDir)) {
            $vendorDir = realpath(dirname(__DIR__, 4));
        }

        if (is_dir($vendorDir)) {
            $projectRoot = realpath(dirname($vendorDir));
            $frameworkRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src');

            $runningInMeros = getenv('MEROS_ENVIRONMENT') === 'true';
            if ($runningInMeros) {
                $wordpressRoot = realpath(dirname($projectRoot, 3));
            }
        }

        $keysDir = $projectRoot . DIRECTORY_SEPARATOR . '.devcontainer' . DIRECTORY_SEPARATOR . 'keys';
        $scriptsDir = $frameworkRoot . DIRECTORY_SEPARATOR . 'Scripts';
        $stubDir = $frameworkRoot . DIRECTORY_SEPARATOR . 'stubs';
        $loaded = is_dir($vendorDir) &&
            is_dir($projectRoot) &&
            is_dir($keysDir) &&
            is_dir($frameworkRoot) &&
            is_dir($scriptsDir) &&
            is_dir($stubDir)
            ? true
            : false;

        if ($loaded) {
            return [
                'wordpressRoot' => $wordpressRoot,
                'projectRoot' => $projectRoot,
                'vendorDir' => $vendorDir,
                'frameworkRoot' => $frameworkRoot,
                'keysDir' => $keysDir,
                'scriptsDir' => $scriptsDir,
                'stubDir' => $stubDir,
            ];
        } else {
            return false;
        }
    }

    public static function getConfig(string $fileName, string $projectRoot, string $stubDir): array {
        $configPath = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $fileName . '.php';

        // Check if the actual config file exists
        if (file_exists($configPath)) {
            return require $configPath;
        }

        // Path to the config template stub
        $configStub = $stubDir . DIRECTORY_SEPARATOR . ucfirst($fileName) . '.stub';

        if (! file_exists($configStub)) {
            return [];
        }

        // Ensure the config directory exists
        if (! is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }

        // Create new config file from stub
        if ($fileName === 'environments') {
            $newConfig = copy($configStub, $configPath);
        } else {
            $stubContent = file_get_contents($configStub);
            $newConfig = self::makeFeaturesConfig($stubContent, $configPath);
        }

        if (! $newConfig) {
            return [];
        }

        // Return the loaded configuration, or an empty array if not found/created
        return file_exists($configPath) ? require $configPath : [];
    }

    public static function regenerateFeaturesConfig(
        string $stubDir,
        string $projectRoot,
        array $featuresConfig,
        array $features,
        array $extensions
    ): bool {
        $stubPath = $stubDir . DIRECTORY_SEPARATOR . 'Features.stub';

        if (file_exists($stubPath)) {
            $stub = file_get_contents($stubPath);
            $rendered = str_replace(
                [
                    '{{theme_class}}',
                    '{{features_namespace}}',
                    '{{extensions_namespace}}',
                    '{{features}}',
                    '{{extensions}}',
                ],
                [
                    var_export($featuresConfig['theme_class'] ?? 'App\\Theme', true),
                    var_export($featuresConfig['features_namespace'] ?? 'App\\Features', true),
                    var_export($featuresConfig['extensions_namespace'] ?? 'App\\Extensions', true),
                    self::formatArray($featuresConfig, $features, 'features', 2),
                    self::formatArray($featuresConfig, $extensions, 'extensions', 2),
                ],
                $stub
            );

            // Theme config file path relative to project root
            $featuresConfigFilePath = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'features.php';

            // Ensure the directory exists before writing the file
            if (! is_dir(dirname($featuresConfigFilePath))) {
                mkdir(dirname($featuresConfigFilePath), 0755, true);
            }

            if (file_put_contents($featuresConfigFilePath, $rendered) !== false) {
                return true;
            }
            return false;
        }
        return false;
    }

    private static function makeFeaturesConfig( string $stub, string $path ): bool {
        $themeClass = 'App\\Theme';
        $featuresNamespace = 'App\\Features';
        $extensionsNamespace = 'App\\Extensions';
        $features = [];
        $extensions = [];

        $rendered = str_replace(
            [
                '{{theme_class}}',
                '{{features_namespace}}',
                '{{extensions_namespace}}',
                '{{features}}',
                '{{extensions}}',
            ],
            [
                var_export($themeClass, true),
                var_export($featuresNamespace, true),
                var_export($extensionsNamespace, true),
                var_export($features, true),
                var_export($extensions, true),
            ],
            $stub
        );

        return file_put_contents($path, $rendered) !== false;
    }

    private static function formatArray(
        array $featuresConfig,
        array $array,
        string $type,
        int $indentLevel = 2
    ): string {
        $indent = str_repeat('    ', $indentLevel);
        $lines = ['['];

        $array = array_unique(array_merge(
            array_values($featuresConfig[$type] ?? []),
            array_values($array)
        ));

        foreach ($array as $value) {
            $formattedValue = var_export($value, true);
            $lines[] = "{$indent}{$formattedValue},";
        }

        $lines[] = str_repeat('    ', $indentLevel - 1) . ']';

        return implode("\n", $lines);
    }
}
