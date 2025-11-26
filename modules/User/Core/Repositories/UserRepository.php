<?php

declare(strict_types=1);

namespace Modules\User\Core\Repositories;

use Ivi\Core\ORM\QueryBuilder;
use Ivi\Core\ORM\Repository;
use Modules\User\Core\Models\User;
use Modules\User\Core\Factories\UserFactory;
use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\ValueObjects\Email;
use Modules\User\Core\ValueObjects\Role;
use RuntimeException;

class UserRepository extends Repository
{
    protected function modelClass(): string
    {
        return User::class;
    }

    /**
     * Récupère un utilisateur par ID avec ses rôles et infos complémentaires
     */
    public function findById(int $id): ?User
    {
        if ($id <= 0) return null;

        // Récupération du user
        $userRow = User::query()
            ->where('id = ?', $id)
            ->first();
        if (!$userRow) return null;

        // Récupération des rôles
        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $id)
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findByUsername(string $username): ?User
    {
        if (!$username) return null;

        $userRow = User::query()
            ->where('username = ?', $username)
            ->first();
        if (!$userRow) return null;

        // On peut aussi récupérer les rôles
        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findByEmail(string|Email $email): ?User
    {
        $emailStr = $email instanceof Email ? (string)$email : $email;
        if (!$emailStr) return null;

        $userRow = User::query()
            ->where('email = ?', $emailStr)
            ->first();
        error_log('Fetched userRow: ' . json_encode($userRow));

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function updateAccessToken(User $user): void
    {
        if (!$user->getId()) {
            throw new \RuntimeException("Cannot update token of unsaved user.");
        }

        User::query()
            ->where('id = ?', $user->getId())
            ->update([
                'access_token' => $user->getAccessToken()
            ]);
    }

    // public function createRememberMeToken(User $user): void
    // {
    //     $selector = bin2hex(random_bytes(9));
    //     $validator = bin2hex(random_bytes(32));
    //     $validatorHash = hash('sha256', $validator);

    //     $expires = (new \DateTime())->modify('+30 days')->format('Y-m-d H:i:s');

    //     QueryBuilder::table('remember_tokens')->insert([
    //         'user_id'       => $user->getId(),
    //         'selector'      => $selector,
    //         'validator_hash' => $validatorHash,
    //         'expires_at'    => $expires,
    //         'ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
    //         'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
    //     ]);

    //     setcookie(
    //         'remember',
    //         "$selector:$validator",
    //         [
    //             'expires'  => strtotime($expires),
    //             'path'     => '/',
    //             'secure'   => true,
    //             'httponly' => true,
    //             'samesite' => 'Lax'
    //         ]
    //     );
    // }

    // private function autoLoginFromRememberMe(): void
    // {
    //     [$selector, $validator] = explode(':', $_COOKIE['remember']);

    //     $row = QueryBuilder::table('remember_tokens')
    //         ->where('selector = ?', $selector)
    //         ->where('expires_at > NOW()')
    //         ->first();

    //     if (!$row) return;

    //     if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
    //         // possible vol de cookie → supprimer tous les remember tokens de l’utilisateur
    //         QueryBuilder::table('remember_tokens')
    //             ->where('user_id = ?', $row['user_id'])
    //             ->delete();
    //         return;
    //     }

    //     $user = $this->userRepository->find($row['user_id']);
    //     if (!$user) return;

    //     // Regénère la session
    //     session_regenerate_id(true);

    //     $_SESSION['unique_id']  = $user->getId();
    //     $_SESSION['user_email'] = $user->getEmail();
    //     $_SESSION['roles']      = $user->getRoleNames();

    //     // Regénère un JWT
    //     $token = UserHelper::generateJwt($user, 3600);
    //     $user->setAccessToken($token);
    //     $this->repository->updateAccessToken($user);

    //     setcookie('token', $token, [
    //         'expires'  => time() + 3600,
    //         'path'     => '/',
    //         'secure'   => true,
    //         'httponly' => true,
    //         'samesite' => 'Lax'
    //     ]);
    // }

    // Dans ton logout() :

    // setcookie('remember', '', time() - 3600, '/');

    // QueryBuilder::table('remember_tokens')
    //     ->where('user_id = ?', $user->getId())
    //     ->delete();

    public function delete(int $id): void
    {
        $user = $this->find($id);
        if ($user) {
            $user->delete();
        }
    }

    /**
     * Crée un utilisateur avec ses rôles déjà présents dans $user->getRoles()
     */
    public function createWithRoles(User $user): User
    {
        try {
            // Insert utilisateur (insert retourne déjà l’ID)
            $userId = (int) User::query()->insert($user->toArray());
            $user->setId($userId);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new \RuntimeException('Email already exists.');
            }
            throw $e;
        }

        // Insert roles pivot
        foreach ($user->getRoles() as $role) {
            QueryBuilder::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $role->getId()
            ]);
        }

        return $user;
    }

    /**
     * Sauvegarde un utilisateur (INSERT ou UPDATE)
     */
    public function save(User $user): User
    {
        if ($user->getId()) {
            $this->update($user);
            return $user;
        }

        // Nouvel utilisateur → gestion rôles
        return $this->createWithRoles($user);
    }

    /**
     * Met à jour un utilisateur existant
     */
    public function update(User $user): void
    {
        // update via ORM
        $user->save();
    }

    /**
     * Met à jour un champ spécifique d’un utilisateur (ex: photo, cover_photo) avec Cloudinary public_id optionnel.
     */
    public function updateField(int $userId, string $field, ?string $value, ?string $publicId = null): bool
    {
        $user = $this->findById($userId);
        if (!$user) return false;

        switch ($field) {
            case 'photo':
                $user->setPhoto($value);
                $user->setPhotoPublicId($publicId);
                break;
            case 'cover_photo':
                $user->setCoverPhoto($value);
                $user->setCoverPhotoPublicId($publicId);
                break;
            default:
                return false; // champ non supporté
        }

        try {
            $this->update($user);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }


    /**
     * Synchronise les rôles d’un utilisateur.
     *
     * @param User $user
     * @param array<string|Role> $roles Liste de rôles (noms ou objets Role)
     */
    public function syncRoles(User $user, array $roles): void
    {
        $userId = $user->getId();

        // Supprime tous les anciens rôles
        User::query('user_roles')->where('user_id = ?', $userId)->delete();

        $roleObjects = $this->normalizeRoles($roles);

        // Ajout des nouveaux rôles en base et dans l’objet
        foreach ($roleObjects as $role) {
            User::query('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $role->getId()
            ]);
        }

        $user->clearRoles();
        foreach ($roleObjects as $role) {
            $user->addRole($role);
        }
    }

    /**
     * Normalise une liste de rôles (strings ou objets Role) en objets Role.
     * Ajoute 'user' si aucun rôle valide trouvé.
     *
     * @param array<string|Role> $roles
     * @return Role[]
     */
    private function normalizeRoles(array $roles): array
    {
        $roleObjects = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleRow = User::query('roles')->where('name = ?', strtolower(trim($role)))->first();
                if (!$roleRow) {
                    $roleRow = User::query('roles')->where('name = ?', 'user')->first();
                }
                $roleObjects[] = new Role((int)$roleRow['id'], $roleRow['name']);
            } elseif ($role instanceof Role) {
                $roleObjects[] = $role;
            }
        }

        // Fallback sur 'user' si vide
        if (empty($roleObjects)) {
            $roleRow = User::query('roles')->where('name = ?', 'user')->first();
            $roleObjects[] = new Role((int)$roleRow['id'], $roleRow['name']);
        }

        return $roleObjects;
    }


    public function findUserWithStatsById(int $id): ?User
    {
        $userRow = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->leftJoin('user_location ul', 'ul.user_id = users.id')
            ->leftJoin('cities ci', 'ci.id = ul.city_id')
            ->leftJoin('countries co', 'co.id = ul.country_id')
            ->where('users.id = ?', $id)
            ->groupBy('users.id')
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $id)
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findUserWithStatsByEmail(string $email): ?User
    {
        $userRow = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->leftJoin('user_location ul', 'ul.user_id = users.id')
            ->leftJoin('cities ci', 'ci.id = ul.city_id')
            ->leftJoin('countries co', 'co.id = ul.country_id')
            ->where('users.email = ?', $email)
            ->groupBy('users.id')
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findUserWithStatsByUsername(string $username): ?User
    {
        $userRow = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->leftJoin('user_location ul', 'ul.user_id = users.id')
            ->leftJoin('cities ci', 'ci.id = ul.city_id')
            ->leftJoin('countries co', 'co.id = ul.country_id')
            ->where('users.username = ?', $username)
            ->groupBy('users.id')
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    public function findUserWithStatsByResetToken(string $resetToken): ?User
    {
        $userRow = User::query()
            ->where('users.refresh_token = ?', $resetToken)
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    /**
     * Récupère tous les utilisateurs avec stats, localisation, rôles et nombre de produits.
     *
     * @param int|null $limit Limite optionnelle du nombre d'utilisateurs
     * @param bool $onlyActiveCities Filtrer les utilisateurs dont la ville est affichée
     * @param int|null $minProducts Nombre minimal de produits (null = aucun filtre)
     * @return iterable<User>
     */
    public function getUsersWithStats(?int $limit = null, bool $onlyActiveCities = true, ?int $minProducts = null): iterable
    {
        $qb = User::query()
            ->select(
                'users.*',
                'c.name AS city_name',
                'co.name AS country_name',
                'co.image_url AS country_image_url',
                'COUNT(p.id) AS product_count'
            )
            ->join('user_location ul', 'ul.user_id = users.id')
            ->join('cities c', 'c.id = ul.city_id')
            ->join('countries co', 'co.id = ul.country_id')
            ->leftJoin('products p', 'p.user_id = users.id')
            ->groupBy('users.id')
            ->orderBy('users.created_at', 'DESC');

        if ($onlyActiveCities) {
            $qb->where('ul.show_city = ?', 1);
        }

        if ($limit !== null) {
            $qb->limit(max(1, $limit));
        }

        foreach ($qb->get() as $row) {
            // Filtrer en PHP si minProducts défini
            if ($minProducts !== null && (($row['product_count'] ?? 0) < $minProducts)) {
                continue;
            }

            try {
                $user = $this->normalizeUserWithRoles($row);
                yield $user;
            } catch (\Exception $e) {
                error_log("User mapping failed: " . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Récupère et hydrate un utilisateur avec ses rôles et stats.
     *
     * @param array $row Données utilisateur brutes
     * @return User
     */
    private function normalizeUserWithRoles(array $row): User
    {
        // Récupération des rôles
        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $row['id'])
            ->get();

        $user = UserFactory::createFromDb($row, $rolesRows);

        // Hydratation des champs calculés / jointés
        if (method_exists($user, 'setProductCount')) {
            $user->setProductCount((int)($row['product_count'] ?? 0));
        }
        if (method_exists($user, 'setCityName')) {
            $user->setCityName($row['city_name'] ?? null);
        }
        if (method_exists($user, 'setCountryImageUrl')) {
            $user->setCountryImageUrl($row['country_image_url'] ?? null);
        }

        return $user;
    }

    /**
     * Récupère tous les utilisateurs avec au moins 2 produits et infos de localisation.
     *
     * @param int|null $limit Optionnel, limite le nombre de résultats
     * @return iterable<User>
     */
    public function findAll(?int $limit = null): iterable
    {
        return $this->getUsersWithStats($limit, true, 2);
    }

    /**
     * Récupère les utilisateurs récents avec stats, localisation, rôles et nombre de produits.
     *
     * @param int|null $limit Optionnel, limite le nombre de résultats
     * @param bool $onlyActiveCities Filtrer les utilisateurs dont la ville est affichée
     * @return iterable<User>
     */
    public function getUsers(?int $limit = null, bool $onlyActiveCities = true): iterable
    {
        // Appel direct de la fonction centrale avec minProducts = null (aucune limite sur le nombre de produits)
        return $this->getUsersWithStats($limit, $onlyActiveCities, null);
    }

    /**
     * Récupère un utilisateur par son username (insensible à la casse).
     */
    public function findOneByUsername(string $username): ?User
    {
        $userRow = User::query()
            ->where('LOWER(username) = LOWER(?)', $username)
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }

    /**
     * Met à jour le mot de passe d’un utilisateur.
     *
     * @param User $user
     * @param string $newPassword Nouveau mot de passe
     * @param string|null $currentPassword Ancien mot de passe à vérifier (si nécessaire)
     * @return bool
     * @throws \Exception si l'ancien mot de passe est incorrect
     */
    public function updatePassword(User $user, string $newPassword, ?string $currentPassword = null): bool
    {
        $userRow = User::query()->where('id = ?', $user->getId())->first();
        if (!$userRow) return false;

        // Si mot de passe existant et qu'on demande vérification
        if (!empty($userRow['password']) && $currentPassword !== null) {
            if (!UserHelper::verifyPassword($currentPassword, $userRow['password'])) {
                throw new \Exception("Le mot de passe actuel est incorrect.");
            }
        }

        // Hash et mise à jour
        $hashedPassword = UserHelper::hashPassword($newPassword);
        User::query()->where('id = ?', $user->getId())->update([
            'password' => $hashedPassword
        ]);

        return true;
    }

    /**
     * Reset du mot de passe oublié ou Google user.
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        // Appelle la même fonction, sans vérifier l'ancien
        return $this->updatePassword($user, $newPassword, null);
    }

    /**
     * Récupère des vendeurs actifs (rôle 'user').
     */
    public function findSellers(int $limit = 2): array
    {
        $limit = max(1, $limit);

        $rows = User::query()
            ->select('users.*')
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->where('r.name = ?', 'user')
            ->where('users.status = ?', 'active')
            ->limit($limit)
            ->get();

        $sellers = [];
        foreach ($rows as $row) {
            $rolesRows = User::query()
                ->select('r2.id, r2.name')
                ->leftJoin('user_roles ur', 'ur.user_id = users.id')
                ->leftJoin('roles r2', 'r2.id = ur.role_id')
                ->where('users.id = ?', $row['id'])
                ->get();

            $sellers[] = UserFactory::createFromDb($row, $rolesRows);
        }

        return $sellers;
    }

    /**
     * Récupère un utilisateur par rôle (rôle principal ou via user_roles).
     */
    public function findByRoles(string $roleName): ?User
    {
        $userRow = User::query()
            ->leftJoin('roles r', 'r.id = users.role_id')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('(r.name = ? OR r2.name = ?)', [$roleName, $roleName])
            ->first();

        if (!$userRow) return null;

        $rolesRows = User::query()
            ->select('r2.id, r2.name')
            ->leftJoin('user_roles ur', 'ur.user_id = users.id')
            ->leftJoin('roles r2', 'r2.id = ur.role_id')
            ->where('users.id = ?', $userRow['id'])
            ->get();

        return UserFactory::createFromDb($userRow, $rolesRows);
    }
}
