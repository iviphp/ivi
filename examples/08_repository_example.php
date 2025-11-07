<?php

declare(strict_types=1);

use Ivi\Core\ORM\{Repository, Model};

// Model
final class User extends Model
{
    protected static array $fillable = ['name','email','password','active'];
}

// Repository
final class UserRepository extends Repository
{
    /** @return class-string<User> */
    protected function modelClass(): string
    {
        return User::class;
    }

    /** Find by email with a small example */
    public function findByEmail(string $email): ?User
    {
        $row = User::query()->where('email = ?', $email)->first();
        return $row ? new User($row) : null;
    }
}

// Usage
$repo = new UserRepository();

// create
$user = $repo->create([
    'name'     => 'Daisy',
    'email'    => 'daisy@example.com',
    'password' => password_hash('flower', PASSWORD_BCRYPT),
    'active'   => 1,
]);
echo "[REPO::create] id=" . $user->toArray()['id'] . "\n";

// all
$all = $repo->all();
echo "[REPO::all] count=" . count($all) . "\n";

// custom finder
$byEmail = $repo->findByEmail('daisy@example.com');
echo "[REPO::findByEmail] " . json_encode($byEmail?->toArray()) . "\n";
