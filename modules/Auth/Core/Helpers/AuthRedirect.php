<?php

namespace Modules\Auth\Core\Helpers;

use Modules\Auth\Core\Helpers\AuthUser;
use Ivi\Http\RedirectResponse;

class AuthRedirect
{
    /**
     * Retourne l'utilisateur ou null
     */
    public static function user()
    {
        $auth = new AuthUser();
        return $auth->getUser();
    }

    /**
     * Vrai si un user est authentifié
     */
    public static function isAuthenticated(): bool
    {
        return self::user() !== null;
    }

    /**
     * Vrai si l'utilisateur n'est pas connecté
     */
    public static function isGuest(): bool
    {
        return self::user() === null;
    }

    /**
     * Redirige si l'utilisateur est connecté
     */
    public static function redirectIfAuthenticated(string $to = '/user/home'): void
    {
        if (self::isAuthenticated()) {
            RedirectResponse::to($to)->send();
            exit;
        }
    }

    /**
     * Redirige si l'utilisateur est invité (non connecté)
     */
    public static function redirectIfGuest(string $to = '/auth/login'): void
    {
        if (self::isGuest()) {
            RedirectResponse::to($to)->send();
            exit;
        }
    }

    /**
     * Redirige si les rôles ne correspondent pas
     * Exemple: requireRoles(['admin'])
     */
    public static function requireRoles(array $roles, string $redirectTo = '/forbidden'): void
    {
        $user = self::user();

        // Pas connecté → redirection
        if (!$user) {
            RedirectResponse::to('/auth/login')->send();
            exit;
        }

        $userRoles = $user->getRoleNames() ?? [];

        // Vérifier si le user possède au moins 1 des rôles requis
        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                return; // OK
            }
        }

        // Aucun rôle trouvé
        RedirectResponse::to($redirectTo)->send();
        exit;
    }

    /**
     * Forcer un rôle unique (plus simple)
     * Exemple: requireRole('admin')
     */
    public static function requireRole(string $role, string $redirectTo = '/forbidden'): void
    {
        self::requireRoles([$role], $redirectTo);
    }
}
