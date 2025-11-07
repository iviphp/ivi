<?php

declare(strict_types=1);

use Ivi\Core\ORM\{Model, QueryBuilder, Connection};

// Example User model (ActiveRecord-like)
final class User extends Model
{
    protected static array $fillable = ['name','email','password','active'];
}

// -- CREATE
$new = User::create([
    'name'     => 'Charlie',
    'email'    => 'charlie@example.com',
    'password' => password_hash('topsecret', PASSWORD_BCRYPT),
    'active'   => 1,
]);

echo "[CREATE] id=" . $new->toArray()['id'] . "\n";

// -- READ
$found = User::find((int)$new->toArray()['id']);
echo "[FIND] " . json_encode($found?->toArray()) . "\n";

// -- UPDATE
$found->fill(['name' => 'Charles'])->save();
echo "[UPDATE] " . json_encode($found->toArray()) . "\n";

// -- DELETE
$deleted = $found->delete();
echo "[DELETE] " . ($deleted ? 'ok' : 'no-op') . "\n";
