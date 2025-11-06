<?php

declare(strict_types=1);

namespace App\Models;

use Ivi\Core\ORM\Model;

class User extends Model
{
    protected static ?string $table = 'users';
    protected static array $fillable = ['id', 'name', 'email', 'password', 'active'];

    /** @return static[] */
    public static function active(): array
    {
        return static::hydrateMany(
            static::query()->where('active = ?', 1)->get()
        );
    }

    public static function byEmail(string $email): ?static
    {
        $row = static::query()->where('email = ?', $email)->first();
        return $row ? new static($row) : null;
    }
}
