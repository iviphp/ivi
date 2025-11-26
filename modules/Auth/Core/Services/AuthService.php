<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Services;

use Ivi\Core\Utils\FlashMessage;
use Modules\User\Core\Models\User;
use Modules\User\Core\Repositories\UserRepository;
use Modules\User\Core\Helpers\UserHelper;
use Ivi\Http\RedirectResponse;
use Modules\User\Core\Factories\UserFactory;
use Modules\User\Core\Services\BaseService;
use Modules\User\Core\Services\UserSecurityService;
use Modules\User\Core\ValueObjects\Role;

class AuthService extends BaseService
{
    private UserSecurityService $userSecurityService;

    public function __construct(
        UserRepository $repository,
        UserSecurityService $userSecurityService
    ) {
        parent::__construct($repository);
        $this->userSecurityService = $userSecurityService;
    }

    /** Login via email/password avec gestion des tentatives */
    public function loginWithCredentials(string $email, string $password): void
    {
        $lockKey = null;

        try {
            $email = strtolower(trim($email));
            $password = trim($password);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->sendJson(['error' => 'Invalid email format.'], 400);
                return;
            }

            $lockKey = 'login_lock_' . md5($email);
            $this->userSecurityService->acquireLock($lockKey);

            // --- récupérer les tentatives échouées
            $failedData = $this->userSecurityService->getFailedAttempts($email);
            $attempts = $failedData['failed_attempts'];
            $lastFailed = $failedData['last_failed_login'];

            $BLOCK_SECONDS = 600;
            if ($attempts >= 5) {
                $elapsed = $lastFailed ? (time() - strtotime($lastFailed)) : 0;
                if ($elapsed < $BLOCK_SECONDS) {
                    $remainingMin = (int)ceil(($BLOCK_SECONDS - $elapsed) / 60);
                    $this->sendJson([
                        'success'   => false,
                        'error'     => "Account locked. Try again in {$remainingMin} minute(s).",
                        'blocked'   => true,
                        'remaining' => $remainingMin,
                        'attempts'  => $attempts,
                    ], 429);
                    return;
                } else {
                    $this->userSecurityService->resetFailedAttempts($email);
                    $attempts = 0;
                }
            }

            if ($attempts >= 5) {
                $delay = min(2000000, 100000 * pow(2, $attempts - 2));
                usleep((int)$delay);
            }

            $user = $this->repository->findByEmail($email);
            if (!$user || !$user->getPassword() || !UserHelper::verifyPassword($password, $user->getPassword())) {
                $this->userSecurityService->incrementFailedAttempts($email);
                $this->sendJson(['error' => 'Incorrect email or password.'], 401);
                return;
            }

            $this->userSecurityService->resetFailedAttempts($email);

            $token = $this->issueAuthForUser($user);

            FlashMessage::add('success', "Welcome, {$user->getUsername()}!");
            $redirect = $this->withAfterLoginHash($this->safeNextFromRequest('/user/dashboard'));

            $this->sendJson([
                'token' => $token,
                'user' => [
                    'id'       => (int)$user->getId(),
                    'email'    => (string)$user->getEmail(),
                    'username' => $user->getUsername(),
                ],
                'redirect' => $redirect,
            ], 200);
        } catch (\Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $this->sendJson(['error' => 'An unexpected error occurred.'], 500);
        } finally {
            if ($lockKey) {
                $this->userSecurityService->releaseLock($lockKey);
            }
        }
    }

    public function loginWithGoogle(object $googleUser): void
    {
        try {
            // 1️⃣ Normalisation email
            $emailStr = strtolower(trim((string)($googleUser->email ?? '')));
            if (!$emailStr) {
                FlashMessage::add('error', 'Google did not return a valid email.');
                RedirectResponse::to('/login'); // handler de test ou production
                return;
            }

            // 2️⃣ Vérifie si l'utilisateur existe déjà
            $existingUser = $this->repository->findByEmail($emailStr);
            if ($existingUser) {
                $token = $this->issueAuthForUser($existingUser);
                FlashMessage::add('success', 'Welcome back, ' . $existingUser->getUsername() . '!');
                RedirectResponse::to($this->safeNextFromRequest('/'));
                return;
            }

            // 3️⃣ Crée l'utilisateur via UserFactory
            $userData = [
                'fullname'       => $googleUser->name ?? '',
                'email'          => $emailStr,
                'photo'          => $googleUser->picture ?? null,
                'password'       => null,
                'roles'          => [UserHelper::defaultRole()],
                'status'         => 'active',
                'verifiedEmail'  => (bool)($googleUser->verifiedEmail ?? false),
                'coverPhoto'     => 'cover.jpg',
            ];

            $userEntity = UserFactory::createFromArray($userData);

            // 4️⃣ Persistance via repository
            $savedUser = $this->repository->save($userEntity);

            // 5️⃣ Auth + JWT
            $token = $this->issueAuthForUser($savedUser);
            FlashMessage::add('success', 'Welcome, ' . $savedUser->getUsername() . '!');

            // 6️⃣ Redirection sécurisée
            RedirectResponse::to($this->safeNextFromRequest('/finalize-registration'));
        } catch (\Throwable $e) {
            error_log('LoginWithGoogle error: ' . $e->getMessage());
            FlashMessage::add('error', 'An unexpected error occurred.');
            RedirectResponse::to('/login');
        }
    }

    public function loginWithGoogleOAuth(object $googleUser): void
    {
        try {
            $this->captureNextFromGoogleState();

            $email = strtolower(trim((string)($googleUser->email ?? '')));
            if (!$email) {
                FlashMessage::add('error', 'Google did not return a valid email.');
                RedirectResponse::to('/login')->send();
                return; // OK : return simple (void)
            }

            $existingUser = $this->repository->findByEmail($email);
            if ($existingUser) {
                $this->issueAuthForUser($existingUser);
                FlashMessage::add(
                    'success',
                    'Welcome back, ' . ($existingUser->getUsername() ?: $existingUser->getFullname()) . '!'
                );

                $next = $this->safeNextFromRequest('/');
                RedirectResponse::to($this->withAfterLoginHash($next))->send();
                return; // OK
            }

            // ---- NOUVEL UTILISATEUR ----
            $userData = [
                'fullname'      => $googleUser->name ?? '',
                'email'         => $email,
                'photo'         => $googleUser->picture ?? null,
                'password'      => null,
                'roles'         => [new Role(1, 'user')],
                'status'        => 'active',
                'verifiedEmail' => (bool)($googleUser->verifiedEmail ?? false),
                'coverPhoto'    => 'cover.jpg',
            ];

            $userEntity = UserFactory::createFromArray($userData);

            // Persist
            $savedUser = $this->repository->createWithRoles(
                $userEntity,
                $userEntity->getRoles()
            );

            // Auth
            $this->issueAuthForUser($savedUser);

            FlashMessage::add(
                'success',
                'Welcome, ' . ($savedUser->getUsername() ?: $savedUser->getFullname()) . '!'
            );

            $next = $this->safeNextFromRequest('/finalize-registration');

            RedirectResponse::to($this->withAfterLoginHash($next))->send();
            return; // OK

        } catch (\Throwable $e) {
            error_log('Google OAuth login error: ' . $e->getMessage());
            FlashMessage::add('error', 'An error occurred during Google login.');

            RedirectResponse::to('/login')->send();
            return; // OK
        }
    }


    private function captureNextFromGoogleState(): void
    {
        $state = $_GET['state'] ?? null;
        if (!$state) {
            return;
        }

        $decoded = json_decode(base64_decode($state), true) ?: [];

        // Vérifie le token CSRF stocké en session
        if (!empty($decoded['csrf']) && !empty($_SESSION['google_oauth_state_csrf'])) {
            if (!hash_equals($_SESSION['google_oauth_state_csrf'], $decoded['csrf'])) {
                return; // CSRF invalide → on ignore
            }
            $_SESSION['google_oauth_state_csrf'] = null; // supprime le token
        }

        // Stocke le next dans la session pour redirection après login
        if (!empty($decoded['next'])) {
            $_SESSION['post_auth_next'] = $decoded['next'];
        } elseif (!empty($_GET['next'])) {
            $_SESSION['post_auth_next'] = $_GET['next'];
        }
    }

    /** Crée session + JWT pour un utilisateur */
    public function issueAuthForUser(User $user): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        session_regenerate_id(true);

        $_SESSION['unique_id']  = (int)$user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['roles']      = $user->getRoleNames() ?? ['user'];

        $token = UserHelper::generateJwt($user, $this->jwtValidity);

        $user->setAccessToken($token);
        $this->repository->updateAccessToken($user);

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $isLocal = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;

        $cookieOptions = [
            'expires'  => time() + $this->jwtValidity,
            'path'     => '/',
            'secure'   => !$isLocal && $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (!$isLocal && preg_match('/(^|\.)softadastra\.com$/i', $host)) {
            $cookieOptions['domain'] = '.softadastra.com';
        }

        setcookie('token', $token, $cookieOptions);

        return $token;
    }
}
