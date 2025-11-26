<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Dotenv\Dotenv;
use Ivi\Core\Config\Config;

/**
 * Class Loader
 *
 * Bootstraps the application:
 *  - Prepares and loads the .env file
 *  - Initializes the configuration system
 *  - Defines global constants (including Google OAuth)
 *  - Configures Cloudinary if present
 *
 * Usage:
 *   Loader::bootstrap(BASE_PATH);
 */
final class Loader
{
    /**
     * Bootstrap the application
     */
    public static function bootstrap(string $baseDir): void
    {
        self::prepareEnvFile($baseDir);
        self::loadEnv($baseDir);
        self::defineEarlyConstants();
        self::initConfig($baseDir);
        self::defineConstants();
        self::configureCloudinary();
    }

    /**
     * Définir les constantes utilisées dans les fichiers config (avant init Config)
     */
    private static function defineEarlyConstants(): void
    {
        defined('GOOGLE_CLIENT_ID')     || define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
        defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
        defined('GOOGLE_REDIRECT_URI')  || define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? '');
        defined('GOOGLE_SCOPES')       || define('GOOGLE_SCOPES', $_ENV['GOOGLE_SCOPES'] ?? 'email,profile');
    }

    /**
     * Copy .env.example → .env if missing
     */
    private static function prepareEnvFile(string $baseDir): void
    {
        $envPath     = $baseDir . '/.env';
        $examplePath = $baseDir . '/.env.example';

        if (!is_file($envPath) && is_file($examplePath)) {
            @copy($examplePath, $envPath);
            echo "[IVI] Copied .env.example → .env\n";
        }
    }

    /**
     * Load the appropriate .env file
     */
    private static function loadEnv(string $baseDir): void
    {
        $envFile       = '.env';
        $envFromServer = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;

        if ($envFromServer) {
            $candidate = ".env.{$envFromServer}";
            if (is_file($baseDir . DIRECTORY_SEPARATOR . $candidate)) {
                $envFile = $candidate;
            }
        }

        $dotenv = Dotenv::createImmutable($baseDir, $envFile);
        $dotenv->safeLoad();
    }

    /**
     * Initialize configuration system
     */
    private static function initConfig(string $baseDir): void
    {
        Config::init($baseDir . '/config');
    }

    /**
     * Define global constants from config
     */
    private static function defineConstants(): void
    {
        defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));
        defined('VIEWS')     || define('VIEWS', BASE_PATH . '/views');

        defined('IVI_LOG_FILE') || define('IVI_LOG_FILE', BASE_PATH . '/storage/logs/ivi.log');

        defined('APP_ENV')   || define('APP_ENV', Config::get('app.env', 'prod'));
        defined('JWT_SECRET') || define('JWT_SECRET', Config::get('app.jwt_secret', 'change_me'));

        // Cloudinary
        $cloudinary = Config::get('cloudinary', []);
        defined('CLOUDINARY_FOLDER') || define('CLOUDINARY_FOLDER', $cloudinary['folder'] ?? 'softadastra/good');

        // Google OAuth (déjà défini plus tôt mais on peut harmoniser les valeurs avec config)
        $google = Config::get('google', []);
        defined('GOOGLE_CLIENT_ID')     || define('GOOGLE_CLIENT_ID', $google['client_id'] ?? GOOGLE_CLIENT_ID);
        defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', $google['client_secret'] ?? GOOGLE_CLIENT_SECRET);
        defined('GOOGLE_REDIRECT_URI')  || define('GOOGLE_REDIRECT_URI', $google['redirect_uri'] ?? GOOGLE_REDIRECT_URI);

        $scopes = $google['scopes'] ?? GOOGLE_SCOPES;
        if (is_array($scopes)) {
            $scopes = implode(' ', $scopes);
        } else {
            $scopes = str_replace(',', ' ', $scopes);
        }
        defined('GOOGLE_SCOPES') || define('GOOGLE_SCOPES', $scopes);
    }

    /**
     * Configure Cloudinary if class exists
     */
    private static function configureCloudinary(): void
    {
        if (!class_exists(\Cloudinary\Configuration\Configuration::class)) {
            return;
        }

        $cloud = Config::get('cloudinary', []);
        $cloudName = $cloud['cloud_name'] ?? '';
        $apiKey    = $cloud['api_key'] ?? '';
        $apiSecret = $cloud['api_secret'] ?? '';

        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            return;
        }

        \Cloudinary\Configuration\Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => ['secure' => true],
        ]);
    }
}
