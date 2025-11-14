<?php

namespace Ivi\Core\Security;

class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Génère et stocke un token CSRF sécurisé.
     */
    public static function generateToken(bool $forceRegenerate = false): string
    {
        if ($forceRegenerate || empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Vérifie un token CSRF (utilise hash_equals pour éviter timing attacks).
     *
     * @throws \RuntimeException
     */
    public static function verifyToken(?string $token, bool $invalidateAfter = true): void
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        if (!$token || !$stored || !hash_equals($stored, $token)) {
            throw new \RuntimeException('Invalid CSRF token');
        }

        if ($invalidateAfter) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }

    /**
     * Invalide volontairement le token (utile après un logout).
     */
    public static function invalidate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
