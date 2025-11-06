<?php

declare(strict_types=1);

namespace App\Repositories;

use Ivi\Core\ORM\Repository;
use App\Models\User;

final class UserRepository extends Repository
{
    protected function modelClass(): string
    {
        return User::class;
    }
}
