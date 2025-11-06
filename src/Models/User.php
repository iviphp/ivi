<?php

declare(strict_types=1);

namespace App\Models;

use Ivi\Core\ORM\Model;

final class User extends Model
{
    protected static ?string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['name', 'email', 'password', 'created_at', 'updated_at'];
}
