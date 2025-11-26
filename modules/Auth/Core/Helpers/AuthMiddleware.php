<?php

namespace Modules\Auth\Core\Helpers;

use Exception;
use Modules\User\Core\Models\User;

class AuthMiddleware
{
    /**
     * Middleware principal pour valider le JWT et récupérer l'utilisateur.
     *
     * @return User|null L'utilisateur authentifié ou null si non authentifié.
     */
    public static function handle(): ?User
    {
        $authUser = new AuthUser();
        $user = $authUser->getUser();

        if (!$user) {
            // Optionnel : lancer une exception ou renvoyer une réponse 401
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        return $user;
    }

    /**
     * Vérifie qu'un utilisateur a un rôle spécifique.
     *
     * @param string|array $roles Rôle ou liste de rôles autorisés
     * @return User|null
     * @throws Exception
     */
    public static function requireRole($roles): ?User
    {
        $user = self::handle();
        $roles = (array)$roles; // forcer en tableau

        foreach ($roles as $role) {
            if (in_array($role, $user->getRoleNames(), true)) {
                return $user;
            }
        }

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// use Modules\User\Core\Auth\AuthMiddleware;

// // Route protégée, accessible à tout utilisateur authentifié
// $user = AuthMiddleware::handle();
// echo "Bonjour " . $user->getFullName();

// // Route accessible seulement aux admins
// $admin = AuthMiddleware::requireRole('admin');
// echo "Bienvenue admin " . $admin->getFullName();
