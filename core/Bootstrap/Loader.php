<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Dotenv\Dotenv;

final class Loader
{
    public static function bootstrap(string $baseDir): void
    {
        self::prepareEnvFile($baseDir);
        self::loadEnv($baseDir);
        self::defineConstants($baseDir);
        self::configureCloudinary();
    }

    /**
     * Si aucun .env n'existe, on copie .env.example
     */
    private static function prepareEnvFile(string $baseDir): void
    {
        $envPath       = $baseDir . '/.env';
        $examplePath   = $baseDir . '/.env.example';

        if (!is_file($envPath) && is_file($examplePath)) {
            @copy($examplePath, $envPath);
            echo "[IVI] Copied .env.example → .env\n";
        }
    }

    /**
     * Chargement du fichier .env (ou .env.{APP_ENV})
     */
    private static function loadEnv(string $baseDir): void
    {
        $envFile = '.env';
        $envFromServer = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;

        if ($envFromServer) {
            $candidate = ".env.{$envFromServer}";
            if (is_file($baseDir . DIRECTORY_SEPARATOR . $candidate)) {
                $envFile = $candidate;
            }
        }

        // safeLoad() ne jette pas d’exception si le fichier est absent
        $dotenv = Dotenv::createImmutable($baseDir, $envFile);
        $dotenv->safeLoad();
    }

    /**
     * Définitions de constantes globales
     */
    private static function defineConstants(string $baseDir): void
    {
        defined('BASE_PATH') || define('BASE_PATH', $baseDir);
        defined('VIEWS')     || define('VIEWS', $baseDir . '/views/');
        defined('APP_ENV')   || define('APP_ENV', $_ENV['APP_ENV'] ?? 'prod');
    }

    /**
     * Configuration automatique de Cloudinary si présent
     */
    private static function configureCloudinary(): void
    {
        if (!class_exists(\Cloudinary\Configuration\Configuration::class)) {
            return;
        }

        $cloud = [
            'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'] ?? '',
            'api_key'    => $_ENV['CLOUDINARY_API_KEY'] ?? '',
            'api_secret' => $_ENV['CLOUDINARY_API_SECRET'] ?? '',
        ];

        if ($cloud['cloud_name'] === '' || $cloud['api_key'] === '' || $cloud['api_secret'] === '') {
            return;
        }

        \Cloudinary\Configuration\Configuration::instance([
            'cloud' => $cloud,
            'url'   => ['secure' => true],
        ]);
    }
}
