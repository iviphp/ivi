<?php

namespace Modules\User\Core\Factories;

use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\Models\User;
use Modules\User\Core\ValueObjects\Email;
use Modules\User\Core\ValueObjects\Role;

class UserFactory
{
    /**
     * Crée un utilisateur à partir de données brutes (array)
     *
     * @param array $data
     *   Clés possibles : id, fullname, email, photo, password, roles (array de Role), status,
     *   verifiedEmail, coverPhoto, accessToken, refreshToken, bio, phone, username,
     *   cityName, countryName, countryImageUrl
     *
     * @return User
     */
    public static function createFromArray(array $data): User
    {
        // Création du Value Object Email
        $email = new Email($data['email']);

        // Création des rôles
        $roles = [];
        if (!empty($data['roles'])) {
            foreach ($data['roles'] as $roleData) {
                // $roleData peut être un objet Role déjà ou un tableau ['id' => x, 'name' => y]
                if ($roleData instanceof Role) {
                    $roles[] = $roleData;
                } elseif (is_array($roleData) && isset($roleData['id'], $roleData['name'])) {
                    $roles[] = new Role($roleData['id'], $roleData['name']);
                }
            }
        }

        $username = $data['username'] ?? strtolower(preg_replace('/\s+/', '', $data['fullname'] ?? 'user'));

        return new User(
            $data['fullname'] ?? '',
            $email,
            $data['photo'] ?? null,
            $data['password'] ?? null,
            $roles,
            $data['status'] ?? 'active',
            (int) ($data['verifiedEmail'] ?? 0),
            $data['coverPhoto'] ?? null,
            $data['accessToken'] ?? null,
            $data['refreshToken'] ?? null,
            $data['bio'] ?? null,
            $data['phone'] ?? null,
            $username, // <--- ici
            $data['cityName'] ?? null,
            $data['countryName'] ?? null,
            $data['countryImageUrl'] ?? null,
            $data['id'] ?? null
        );
    }

    /**
     * Crée un utilisateur à partir d'une ligne DB (fetch)
     *
     * @param array $dbRow
     * @param array $rolesDB Ligne(s) de roles depuis user_roles JOIN roles
     * @return User
     */
    public static function createFromDb(array $dbRow, array $rolesDB = []): User
    {
        // 🔹 Logging pour debug
        error_log("Fetched userRow: " . json_encode($dbRow));

        $roles = [];
        foreach ($rolesDB as $r) {
            if (isset($r['id'], $r['name'])) {
                $roles[] = new Role((int)$r['id'], $r['name']);
            }
        }

        // Si aucun rôle récupéré depuis la DB, assigner le rôle par défaut
        if (empty($roles)) {
            $roles[] = UserHelper::defaultRole();
        }

        // Crée l'utilisateur sans ID (createFromArray peut ne pas gérer 'id')
        $user = self::createFromArray([
            'fullname'      => $dbRow['fullname'],
            'email'         => $dbRow['email'],
            'photo'         => $dbRow['photo'] ?? null,
            'password'      => $dbRow['password'] ?? null,
            'roles'         => $roles,
            'status'        => $dbRow['status'] ?? 'active',
            'verifiedEmail' => (bool) ($dbRow['verified_email'] ?? false),
            'coverPhoto'    => $dbRow['cover_photo'] ?? null,
            'accessToken'   => $dbRow['access_token'] ?? null,
            'refreshToken'  => $dbRow['refresh_token'] ?? null,
            'bio'           => $dbRow['bio'] ?? null,
            'phone'         => $dbRow['phone'] ?? null,
            'username'      => $dbRow['username'] ?? null,
            'cityName'      => $dbRow['city_name'] ?? null,
            'countryName'   => $dbRow['country_name'] ?? null,
            'countryImageUrl' => $dbRow['country_image_url'] ?? null,
        ]);

        // 🔹 Fix de l'ID si createFromArray ne le gère pas
        $user->setId((int)$dbRow['id']);
        error_log("Created User object with ID: " . $user->getId());

        return $user;
    }
}
