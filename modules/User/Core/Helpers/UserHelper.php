<?php

declare(strict_types=1);

namespace Modules\User\Core\Helpers;

use Ivi\Core\Jwt\JWT;
use Modules\User\Core\Repositories\UserRepository;

use Modules\User\Core\Models\User;
use Modules\User\Core\ValueObjects\Role;

class UserHelper
{
    private const PASSWORD_ALGO = PASSWORD_BCRYPT;
    private const PASSWORD_OPTIONS = ['cost' => 12];

    /**
     * Hash un mot de passe.
     */
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, self::PASSWORD_ALGO, self::PASSWORD_OPTIONS);
    }

    /**
     * Vérifie un mot de passe.
     */
    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Vérifie si le hash est obsolète.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::PASSWORD_ALGO, self::PASSWORD_OPTIONS);
    }

    /**
     * Génère un token aléatoire sécurisé.
     */
    // public static function generateToken(int $length = 64): string
    // {
    //     return bin2hex(random_bytes(intdiv($length, 2)));
    // }

    /**
     * Politique de mot de passe stricte.
     */
    public static function validatePasswordPolicy(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/\d/', $password);
    }

    /**
     * Renvoie la photo de profil d’un utilisateur.
     * Gère:
     * - URL externe
     * - avatar google
     * - avatar local
     * - fallback dynamique
     */
    public static function getProfileImage(?string $photo): string
    {
        $default = "/public/images/profile/avatar.jpg";

        // Aucune image
        if (empty($photo)) {
            return $default;
        }

        // URL externe
        if (filter_var($photo, FILTER_VALIDATE_URL)) {

            // Optimisation: éviter get_headers(), seulement google checké
            if (strpos($photo, 'googleusercontent.com') !== false) {
                return $photo; // Google avatars sont toujours accessibles
            }

            return $photo;
        }

        // Image locale
        $path = "/public/images/profile/" . $photo;
        $full = $_SERVER['DOCUMENT_ROOT'] . $path;

        if (is_file($full)) {
            return $path;
        }

        return $default;
    }

    /**
     * Génération de JWT propre et configurable.
     */
    public static function generateJwt(User $user, int $validitySeconds): string
    {
        $jwt = new JWT();

        // Payload normalisé
        $payload = [
            'sub'   => $user->getId(),
            'name'  => $user->getFullName(),
            'email' => (string) $user->getEmail(),
            'roles' => $user->getRoleNames(), // ✔ NOM CORRECT
        ];

        // Options conformes à ta classe JWT
        $options = [
            'alg'      => 'HS256',
            'key'      => JWT_SECRET,   // secret JWT
            'validity' => $validitySeconds,    // durée en secondes
        ];

        return $jwt->generate($payload, $options);
    }

    /**
     * Retourne uniquement le nom de famille (tout sauf le premier mot).
     */
    public static function lastName(string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        $parts = explode(' ', $fullName);

        if (count($parts) <= 1) {
            return '';
        }

        array_shift($parts);
        return implode(' ', $parts);
    }

    /**
     * Formatage d’un nom complet :
     * - trim + suppression espaces multiples
     * - maximum 2 mots
     * - capitalisation UTF-8 correcte
     */
    public static function formatFullName(string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        $parts = explode(' ', $fullName);
        $parts = array_slice($parts, 0, 2);

        return mb_convert_case(implode(' ', $parts), MB_CASE_TITLE, "UTF-8");
    }

    /**
     * Retourne un username propre :
     * - minuscules
     * - uniquement lettres/chiffres
     */
    public static function formatUsername(string $username): string
    {
        $username = strtolower($username);
        return preg_replace('/[^a-z0-9]/u', '', $username);
    }

    /**
     * Génère automatiquement un username unique :
     * basé sur prénom+nom, nettoyé, suffixe numérique si pris.
     */
    public static function generateUsername(string $fullName, UserRepository $userRepository): string
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $firstTwo = array_slice($parts, 0, 2);

        $usernameBase = strtolower(implode('', $firstTwo));
        $username = self::formatUsername($usernameBase);

        if ($username === '') {
            $username = 'user';
        }

        $unique = $username;
        $counter = 1;

        while ($userRepository->findByUsername($unique) !== null) {
            $unique = $username . $counter;
            $counter++;

            if ($counter > 9999) {
                $unique = $username . bin2hex(random_bytes(2));
                break;
            }
        }

        return $unique;
    }

    /**
     * Status par défaut pour un utilisateur.
     */
    public static function defaultStatus(): string
    {
        return 'active';
    }

    /**
     * Photo de couverture par défaut.
     */
    public static function defaultCover(): string
    {
        return 'cover.png';
    }

    /**
     * Description / bio par défaut.
     */
    public static function defaultBio(): string
    {
        return 'Hello! I am new here.';
    }

    /**
     * Rôle par défaut.
     */
    public static function defaultRole(): Role
    {
        return new Role(1, 'user');
    }

    /** Normalisation du téléphone en E.164 pour UG & DRC */
    public static function normalizeE164(string $raw): string
    {
        $v = preg_replace('/[^\d+]/', '', trim($raw));
        if ($v === '') return '';

        // Uganda
        if (preg_match('/^(?:\+256|256|0?7)(\d{8})$/', $v, $m)) {
            return '+256' . $m[1];
        }
        // DRC
        if (preg_match('/^(?:\+243|243|0?[89])(\d{8})$/', $v, $m)) {
            return '+243' . $m[1];
        }

        return $v;
    }
}
