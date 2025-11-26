<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Database\Seeders;

final class AuthSeeder
{
    public function run(): void
    {
        echo "[seed] auth ok\n";
    }
}

return new AuthSeeder();
