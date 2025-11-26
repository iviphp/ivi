<?php

declare(strict_types=1);

namespace Modules\User\Core\Services;

use User;

class UserSecurityService
{
    /** Récupère tentatives depuis DB */
    public function getFailedAttempts(string $email): array
    {
        $userRow = User::query()->where('email = ?', $email)->first();
        $loginRow = User::query('login_attempts')->where('email = ?', $email)->first();

        return [
            'failed_attempts' => (int)($loginRow['failed_attempts'] ?? $userRow['failed_attempts'] ?? 0),
            'last_failed_login' => $loginRow['last_failed_login'] ?? $userRow['last_failed_login'] ?? null,
        ];
    }

    // public function getFailedAttempts(string $email): array
    // {
    //     $row = User::query('login_attempts')->where('email = ?', $email)->first();
    //     return [
    //         'failed_attempts'   => $row['failed_attempts'] ?? 0,
    //         'last_failed_login' => $row['last_failed_login'] ?? null,
    //     ];
    // }

    public function incrementFailedAttempts(string $email): void
    {
        // Récupère les données actuelles
        $user = User::query()->where('email = ?', $email)->first();
        $loginAttempt = User::query('login_attempts')->where('email = ?', $email)->first();

        // Calcul des nouvelles valeurs
        $failedAttempts = ($user['failed_attempts'] ?? 0) + 1;
        $lastFailedLogin = date('Y-m-d H:i:s');

        // Mise à jour atomique via ORM
        if ($user) {
            User::query()
                ->where('email = ?', $email)
                ->update([
                    'failed_attempts'   => $failedAttempts,
                    'last_failed_login' => $lastFailedLogin
                ]);
        }

        if ($loginAttempt) {
            User::query('login_attempts')
                ->where('email = ?', $email)
                ->update([
                    'failed_attempts'   => $loginAttempt['failed_attempts'] + 1,
                    'last_failed_login' => $lastFailedLogin
                ]);
        } else {
            User::query('login_attempts')->insert([
                'email'             => $email,
                'failed_attempts'   => 1,
                'last_failed_login' => $lastFailedLogin
            ]);
        }
    }

    public function resetFailedAttempts(string $email): void
    {
        // Reset des tentatives dans users
        User::query()
            ->where('email = ?', $email)
            ->update([
                'failed_attempts'   => 0,
                'last_failed_login' => null
            ]);

        // Supprime les entrées login_attempts
        User::query('login_attempts')
            ->where('email = ?', $email)
            ->delete();
    }

    /**
     * Tente d’acquérir un verrou sur une clé.
     * @param string $key
     * @param int $timeout en secondes
     * @return bool true si verrou acquis
     */
    public function acquireLock(string $key, int $timeout = 5): bool
    {
        $start = time();
        while (time() - $start < $timeout) {
            try {
                User::query('locks')->insert([
                    'lock_key' => $key,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                // Insert réussi → verrou acquis
                return true;
            } catch (\Exception $e) {
                // clé déjà existante → attendre et réessayer
                usleep(100_000); // 0.1s
            }
        }
        return false; // timeout
    }

    /**
     * Libère un verrou sur une clé.
     * @param string $key
     * @return bool true si verrou libéré
     */
    public function releaseLock(string $key): bool
    {
        return User::query('locks')
            ->where('lock_key = ?', $key)
            ->delete() > 0;
    }
}
