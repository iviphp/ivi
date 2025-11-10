<?php

declare(strict_types=1);
/**
 * -----------------------------------------------------------------------------
 * Seeder: MarketCoreSeeder (PHP)
 * -----------------------------------------------------------------------------
 *
 * Example PHP-based seeder using PDO. Useful when you need conditional logic,
 * computed values, or cross-table relations that go beyond plain SQL.
 *
 * Usage (one-off):
 *   php -r "require 'vendor/autoload.php'; (new MarketCoreSeeder())->run();"
 *
 * Requirements:
 *   - PDO DSN resolved from env (MYSQL_DSN / DB_*), or adjust the constructor.
 * -----------------------------------------------------------------------------
 */


namespace Modules\Market\Core\Database\Seeders;

final class MarketCoreSeeder
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $this->makePdoFromEnv();
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function run(): void
    {
        $this->seedSettings();
        $this->seedCategories();
        $this->seedShops();
        echo "[MarketCoreSeeder] Done.\n";
    }

    private function seedSettings(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO market_settings (`key`, `value`)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
        );
        $stmt->execute([
            ':k' => 'market.title',
            ':v' => json_encode(['text' => 'Softadastra Market'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function seedCategories(): void
    {
        $categories = [
            ['slug' => 'mobile-phones', 'name' => 'Mobile Phones & Tablets'],
            ['slug' => 'fashion',       'name' => 'Fashion & Apparel'],
            ['slug' => 'electronics',   'name' => 'Electronics'],
        ];

        $stmt = $this->pdo->prepare(
            "INSERT INTO market_categories (parent_id, slug, `name`, is_active)
             VALUES (NULL, :slug, :name, 1)
             ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), is_active=VALUES(is_active)"
        );

        foreach ($categories as $c) {
            $stmt->execute([':slug' => $c['slug'], ':name' => $c['name']]);
        }
    }

    private function seedShops(): void
    {
        $shops = [
            ['owner_id' => 1, 'slug' => 'alpha-traders', 'name' => 'Alpha Traders', 'country' => 'Uganda', 'city' => 'Kampala'],
            ['owner_id' => 2, 'slug' => 'kivu-boutique', 'name' => 'Kivu Boutique', 'country' => 'DRC',    'city' => 'Goma'],
        ];

        $stmt = $this->pdo->prepare(
            "INSERT INTO market_shops (owner_id, slug, `name`, country, city, is_active)
             VALUES (:owner, :slug, :name, :country, :city, 1)
             ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), is_active=VALUES(is_active)"
        );

        foreach ($shops as $s) {
            $stmt->execute([
                ':owner'   => $s['owner_id'],
                ':slug'    => $s['slug'],
                ':name'    => $s['name'],
                ':country' => $s['country'],
                ':city'    => $s['city'],
            ]);
        }
    }

    private function makePdoFromEnv(): \PDO
    {
        $dsn  = $_ENV['MYSQL_DSN']  ?? $_ENV['DB_DSN']  ?? null;
        $user = $_ENV['MYSQL_USER'] ?? $_ENV['DB_USER'] ?? null;
        $pass = $_ENV['MYSQL_PASS'] ?? $_ENV['DB_PASS'] ?? null;

        if (!$dsn) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $db   = $_ENV['DB_NAME'] ?? 'ivi';
            $charset = 'utf8mb4';
            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
            $user = $user ?? ($_ENV['DB_USERNAME'] ?? 'root');
            $pass = $pass ?? ($_ENV['DB_PASSWORD'] ?? '');
        }

        return new \PDO($dsn, (string) $user, (string) $pass);
    }
}

return new MarketCoreSeeder();
