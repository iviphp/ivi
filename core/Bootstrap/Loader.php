<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Dotenv\Dotenv;

final class Loader
{
    public static function bootstrap(string $baseDir): void
    {
        self::loadEnv($baseDir);
        self::defineConstants($baseDir);
        self::configureCloudinary();
    }

    private static function loadEnv(string $baseDir): void
    {
        $envFile = '.env';
        if (!empty($_SERVER['APP_ENV'])) {
            $candidate = ".env.{$_SERVER['APP_ENV']}";
            if (is_file($baseDir . DIRECTORY_SEPARATOR . $candidate)) {
                $envFile = $candidate;
            }
        }
        $dotenv = Dotenv::createImmutable($baseDir, $envFile);
        $dotenv->load();
    }

    private static function defineConstants(string $baseDir): void
    {
        defined('BASE_PATH') || define('BASE_PATH', $baseDir);
        defined('VIEWS')     || define('VIEWS', $baseDir . '/views/');
        defined('APP_ENV')   || define('APP_ENV', $_ENV['APP_ENV'] ?? 'prod');
    }

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

        // Si rien n’est configuré, on ne fait rien
        if ($cloud['cloud_name'] === '' || $cloud['api_key'] === '' || $cloud['api_secret'] === '') {
            return;
        }

        \Cloudinary\Configuration\Configuration::instance([
            'cloud' => $cloud,
            'url'   => ['secure' => true],
        ]);
    }
}
