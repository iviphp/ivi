<?php

// declare(strict_types=1);

// require_once __DIR__ . '/vendor/autoload.php';

// use Modules\User\Core\Models\User;
// use Modules\User\Core\Repositories\UserRepository;
// use Modules\User\Core\Factories\UserFactory;
// use Modules\User\Core\ValueObjects\Email;
// use Modules\User\Core\ValueObjects\Role;
// use Ivi\Core\ORM\QueryBuilder;

// // ------------------------------------------------------------
// // 1. CRÉATION D'UTILISATEUR
// // ------------------------------------------------------------

// $email = new Email('alice@example.com');
// $userData = [
//     'fullname' => 'Alice Dupont',
//     'email'    => $email,
//     'password' => password_hash('secret', PASSWORD_BCRYPT),
//     'username' => 'alice123',
// ];

// // Utilisation du modèle directement
// $user = User::create($userData);
// echo "User created with ID: " . $user->getId() . PHP_EOL;

// // Avec la factory
// $user2 = UserFactory::createFromArray($userData);
// $user2->save();
// echo "User2 created via factory: " . $user2->getId() . PHP_EOL;

// // ------------------------------------------------------------
// // 2. LECTURE
// // ------------------------------------------------------------

// // Récupérer tous les utilisateurs
// $allUsers = User::all();
// echo "Total users: " . count($allUsers) . PHP_EOL;

// // Récupérer un utilisateur par ID
// $found = User::find($user->getId());
// if ($found) echo "Found user: " . $found->getFullname() . PHP_EOL;

// // FindOrFail (throw exception si inexistant)
// try {
//     $userX = User::findOrFail(99999);
// } catch (\Ivi\Core\Exceptions\ORM\ModelNotFoundException $e) {
//     echo "User not found!" . PHP_EOL;
// }

// // ------------------------------------------------------------
// // 3. MISE À JOUR
// // ------------------------------------------------------------

// $found->setUsername('alice_new');
// $found->save();
// echo "Username updated: " . $found->getUsername() . PHP_EOL;

// // ------------------------------------------------------------
// // 4. SUPPRESSION
// // ------------------------------------------------------------

// $userToDelete = User::find($user2->getId());
// if ($userToDelete) {
//     $userToDelete->delete();
//     echo "User deleted: " . $user2->getId() . PHP_EOL;
// }

// // ------------------------------------------------------------
// // 5. QUERY BUILDER AVANCÉ
// // ------------------------------------------------------------

// // Simple where
// $users = User::query()
//     ->where('status = ?', 'active')
//     ->orderBy('id DESC')
//     ->limit(5)
//     ->get();

// // WhereIn
// $usersIn = User::query()
//     ->whereIn('id', [1, 2, 3])
//     ->get();

// // WhereLike
// $usersLike = User::query()
//     ->whereLike('fullname', '%Alice%')
//     ->get();

// // OR condition
// $usersOr = User::query()
//     ->where('status = ?', 'active')
//     ->orWhere('username = ?', 'bob123')
//     ->get();

// // Join (LEFT JOIN / INNER JOIN)
// $usersWithRoles = User::query()
//     ->select('users.id, users.fullname, r.name as role_name')
//     ->leftJoin('user_roles ur', 'ur.user_id = users.id')
//     ->leftJoin('roles r', 'r.id = ur.role_id')
//     ->where('users.status = ?', 'active')
//     ->get();

// // Raw query
// $rawData = User::query()->raw('SELECT COUNT(*) AS total_users FROM users');
// echo "Total users (raw query): " . $rawData[0]['total_users'] . PHP_EOL;

// // Count
// $totalActive = User::query()->where('status = ?', 'active')->count();
// echo "Active users: " . $totalActive . PHP_EOL;

// // ------------------------------------------------------------
// // 6. PAGINATION
// // ------------------------------------------------------------

// $perPage = 5;
// $page = 2;

// $totalUsers = User::query()->count();
// $items = User::query()
//     ->limit($perPage)
//     ->offset(($page - 1) * $perPage)
//     ->get();

// $pagination = new \Ivi\Core\ORM\Pagination($items, $totalUsers, $perPage, $page);

// echo "Page {$pagination->currentPage}/{$pagination->lastPage}, Items on page: " . count($pagination->items) . PHP_EOL;

// // ------------------------------------------------------------
// // 7. REPOSITORY
// // ------------------------------------------------------------

// $userRepo = new UserRepository();

// // Find with roles
// $userWithRoles = $userRepo->findWithRoles($found->getId());
// if ($userWithRoles) {
//     echo "User with roles: " . $userWithRoles->getFullname() . ", Roles: " . implode(', ', $userWithRoles->getRoleNames()) . PHP_EOL;
// }

// // Create with roles
// $roleUser = new Role(1, 'user');
// $newUser = $userRepo->createWithRoles([
//     'fullname' => 'Charlie',
//     'email'    => new Email('charlie@example.com'),
//     'username' => 'charlie123',
// ], [$roleUser]);

// Orders by
// QueryBuilder::table('users')
//     ->where('status = ?', 'active')
//     ->orderBy('created_at', 'DESC')
//     ->orderBy('id')
//     ->get();


// echo "Created user with roles: " . $newUser->getFullname() . PHP_EOL;

// // Sync roles
// $roleAdmin = new Role(2, 'admin');
// $userRepo->syncRoles($newUser, [$roleUser, $roleAdmin]);
// echo "User roles synced. Total roles now: " . count($newUser->getRoleNames()) . PHP_EOL; -->
