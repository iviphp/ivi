<?php

namespace Modules\Auth\Core\Helpers;

use Ivi\Core\Jwt\JWT;
use Modules\User\Core\Models\User;
use Modules\User\Core\Repositories\UserRepository;
use Modules\User\Core\Helpers\UserHelper;

class AuthUser
{
    private JWT $jwt;
    private ?string $token;

    /**
     * Constructor.
     * @param string|null $token
     * @param JWT|null $jwt
     */
    public function __construct(?string $token = null, ?JWT $jwt = null)
    {
        $this->jwt = $jwt ?? new JWT();
        $this->token = $token ?? $this->extractTokenFromRequest();
    }

    /**
     * Extract JWT token from cookie or Authorization header
     */
    private function extractTokenFromRequest(): ?string
    {
        $token = $_COOKIE['token'] ?? null;

        if (!$token) {
            $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? null;
            if ($hdr && preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) {
                $token = $m[1];
            }
        }

        return $token;
    }

    /**
     * Get JWT payload from current token
     */
    public function getPayload(): ?array
    {
        if (!$this->token) return null;

        try {
            $this->jwt->check($this->token, ['key' => JWT_SECRET]);
            $payload = $this->jwt->getPayload($this->token);
            error_log("JWT payload: " . json_encode($payload));
            return $payload;
        } catch (\Throwable $e) {
            error_log("JWT error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Return current authenticated user or null
     */
    public function getUser(): ?User
    {
        $payload = $this->getPayload();
        if (!$payload || empty($payload['sub'])) return null;

        try {
            $repo = new UserRepository();
            $user = $repo->findById((int)$payload['sub']);
            return ($user && $user->getId() === (int)$payload['sub']) ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate JWT token only (no cookie/session)
     */
    public static function generateToken(User $user, int $validity = 604800): string
    {
        $token = UserHelper::generateJwt($user, $validity);
        $user->setAccessToken($token); // store in object only
        return $token;
    }

    /**
     * Create session + cookie, should be called only after successful authentication
     */
    public static function setSessionAndCookie(User $user, string $token, int $validity = 604800): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        session_regenerate_id(true);

        $_SESSION['unique_id']  = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['roles']      = $user->getRoleNames() ?? [];

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie("token", $token, [
            "expires"  => time() + $validity,
            "path"     => "/",
            "secure"   => $isHttps,
            "httponly" => false,       // <--- IMPORTANT: SPA doit lire le cookie
            "samesite" => "Lax",
        ]);
    }

    /**
     * Log in user (shortcut: generate token + session + cookie)
     */
    public static function login(User $user, int $validity = 604800): string
    {
        $token = self::generateToken($user, $validity);
        self::setSessionAndCookie($user, $token, $validity);
        return $token;
    }

    /**
     * Logout user (clear session + cookie)
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Supprime le cookie token côté client
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,   // cookie HTTP-only pour la SPA
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Check if a given token is expired
     */
    public static function isExpired(string $token): bool
    {
        try {
            $jwt = new JWT();
            $payload = $jwt->getPayload($token);
            if (!isset($payload['exp'])) return false;
            return $payload['exp'] < time();
        } catch (\Throwable) {
            return true; // invalid token is considered expired
        }
    }

    /**
     * Return user from token using optional repository
     */
    public static function user(?string $token = null, ?UserRepository $repo = null): ?User
    {
        $token ??= $_COOKIE['token'] ?? null;
        $repo   ??= new UserRepository();

        if (!$token || !$repo) return null;

        try {
            $jwt = new JWT();
            $payload = $jwt->getPayload($token);
            if (!isset($payload['sub'])) return null;
            return $repo->findById((int)$payload['sub']);
        } catch (\Throwable) {
            return null;
        }
    }
}
