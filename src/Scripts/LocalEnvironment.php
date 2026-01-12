<?php 

namespace MM\Meros\Scripts;

use Composer\IO\IOInterface;
use Dotenv\Dotenv;

class EnvironmentManager {
    private IOInterface $io;
    private string $themePath;
    private string $themeSlug;
    private string $wpPath;
    private string $wpPort;
    private string $wpUrl;
    private string $siteTitle;
    private array  $adminConfig;
    private array  $dbConfig;
    
    public bool $initialised = false;

    private function __construct( string $themePath, IOInterface $io ) {
        $this->io = $io;
        $this->themePath = realpath( $themePath );
        $this->wpPath = realpath( dirname( $this->themePath, 3 ) );
        $this->themeSlug = basename( $this->themePath );

        if (! file_exists($this->wpPath . DIRECTORY_SEPARATOR . 'wp-config.php')) {
            $this->loadEnvironmentVariables();
            $this->setUrl();
            $this->setSiteTitle();
            $this->setAdminConfig();
            $this->setDbConfig();

            if (isset(
                $this->io,
                $this->themePath,
                $this->themeSlug,
                $this->wpPath,
                $this->wpPort,
                $this->wpUrl,
                $this->siteTitle,
                $this->adminConfig,
                $this->dbConfig
            )) {
                $this->initialised = true;
            }
        }
    }

    public static function init( string $themePath, IOInterface $io ): self {
        $instance = new self( $themePath, $io );
        return $instance;
    }

    public function create() {
        if (! $this->initialised) {
            return;
        }


    }

    private function waitForSql() {
        $this->io->write('Waiting for database to be ready...');
        $start = time();
        $lastError = null;

        do {
            $mysqli = new \mysqli(
                $this->dbConfig['host'],
                $this->dbConfig['user'],
                $this->dbConfig['pass'],
                null,
                $this->dbConfig['port'] ?? 3306
            );

            if ($mysqli->connect_errno === 0) {
                $mysqli->close();
                return;
            }

            $lastError = $mysqli->connect_error;
            $mysqli->close();
            sleep(1);
        } while (time() - $start < 60);
        
        $this->io->error('Could not connect to the database: ' . $lastError);
    }

    private function makeConfigFile() {
        $command = sprintf(
            'cd %s && wp config create --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --dbcharset=%s --dbcollate=%s --dbprefix=%s',
            escapeshellarg($this->wpPath),
            escapeshellarg($this->dbConfig['name']),
            escapeshellarg($this->dbConfig['user']),
            escapeshellarg($this->dbConfig['pass']),
            escapeshellarg($this->dbConfig['host']),
            escapeshellarg($this->dbConfig['charset']),
            escapeshellarg($this->dbConfig['collate']),
            escapeshellarg($this->dbConfig['prefix'])
        );

        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->io->error('Failed to create wp-config.php file.');
            return;
        }
    }

    private function makeThemeEnv() {
        $themeEnv = $this->themePath . DIRECTORY_SEPARATOR . '.env';
        $key      = 'base64:' . base64_encode(random_bytes(32));
        $comment  = "# An App Key is required for Livewire functionality";

        if (! file_exists($themeEnv)) {
            $envContent = "{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($themeEnv, $envContent);
        } else {
            $envContent = file_get_contents($themeEnv);

             if (!preg_match('/^APP_KEY=.*$/m', $envContent)) {
                $envContent = rtrim($envContent) . "\n\n{$comment}\nAPP_KEY={$key}\n";
                file_put_contents($themeEnv, $envContent);
            }
        }
    }

    private function loadEnvironmentVariables() {
        $localEnvPath = $this->wpPath . DIRECTORY_SEPARATOR . '.devcontainer' . DIRECTORY_SEPARATOR . '.env';

        if (file_exists($localEnvPath)) {
            $dotenv = Dotenv::createImmutable(dirname($localEnvPath));
            $dotenv->load();
        }
    }

    private function setUrl() {
        $this->wpPort = $_ENV['WP_PORT'] ?? '8000';
        $this->wpUrl  = 'http://localhost:' . $this->wpPort;

        $codespaceName = $_ENV['CODESPACE_NAME'] ?? null;
        if (isset($codespaceName)) {
            $this->wpUrl = "https://{$codespaceName}-80.app.github.dev";
        }
    }

    private function setSiteTitle() {
        $this->siteTitle = $_ENV['SITE_TITLE'] ?? 'Meros WP';
    }

    private function setAdminConfig() {
        $this->adminConfig = [
            'user'     => $_ENV['ADMIN_USER'] ?? 'admin',
            'password' => $_ENV['ADMIN_PASSWORD'] ?? 'password',
            'email'    => $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com',
        ];
    }

    private function setDbConfig() {
        $this->dbConfig = [
            'name'    => $_ENV['DB_NAME'] ?? 'wordpress',
            'user'    => $_ENV['DB_USER'] ?? 'dbuser',
            'pass'    => $_ENV['DB_PASSWORD'] ?? 'dbpassword',
            'host'    => $_ENV['DB_HOST'] ?? 'db',
            'prefix'  => $_ENV['DB_PREFIX'] ?? 'wp_',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collate' => $_ENV['DB_COLLATE'] ?? 'utf8mb4_unicode_ci',
        ];
    }
}