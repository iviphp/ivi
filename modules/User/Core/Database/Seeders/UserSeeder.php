<?php
declare(strict_types=1);

namespace Modules\User\Core\Database\Seeders;

final class UserSeeder
{
    public function run(): void
    {
        echo "[seed] User ok\n";
    }
}

return new UserSeeder();