<?php

namespace Modules\User\Core\Services;

use Exception;
use Ivi\Core\Jwt\JWT;
use Ivi\Core\Security\Csrf;
use Ivi\Core\Utils\FlashMessage;
use Modules\User\Core\Models\User;
use Modules\User\Core\Repositories\UserRepository;
use Ivi\Http\JsonResponse;
use Ivi\Http\RedirectResponse;
use Modules\Auth\Core\Helpers\AuthUser;
use Modules\Auth\Core\Services\AuthService;
use Modules\User\Core\Validator\UserValidator;
use Modules\User\Core\ValueObjects\Email;

class UserService extends BaseService
{
    private UserRegistrationService $registration;
    private AuthService $authService;

    public function __construct(
        UserRegistrationService $registration,
        UserRepository $repository,
        AuthService $authService
    ) {
        parent::__construct($repository);
        $this->registration = $registration;
        $this->authService = $authService;
    }

    public function setJsonResponseHandler(callable $handler): void
    {
        $this->jsonResponseHandler = $handler;
    }

    public function setTokenGenerator(callable $generator): void
    {
        $this->tokenGenerator = $generator;
    }

    /** Login standard via AuthUser helper */
    public function findByEmail(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }

    public function loginUser(User $user): string
    {
        error_log("loginUser() called for user ID: " . $user->getId() . " | email: " . $user->getEmail());
        // Génère seulement le token
        return AuthUser::generateToken($user);
    }

    public function loginWithGoogleOAuth(object $googleUser): void
    {
        // déléguer entièrement
        $this->authService->loginWithGoogleOAuth($googleUser);
    }

    /** Logout */
    public function logout(): void
    {
        AuthUser::logout();
    }

    public function register(string $fullname, string $email, string $password): array
    {
        return $this->registration->register($fullname, $email, $password);
    }

    /** Retourne l’utilisateur connecté ou null */
    public function currentUser(): ?User
    {
        return AuthUser::user(null, $this->repository);
    }

    public function updateUser(array $post): JsonResponse
    {
        $userEntity = $this->getUserEntity();

        $user = $this->repository->findById($userEntity->getId());
        if (!$user) {
            FlashMessage::add('error', 'User not found.');
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        $updatedUserEntity = $this->prepareUpdatedUserEntity($user, $post);

        $validationErrors = $this->validateUserEntity($updatedUserEntity);
        if (!empty($validationErrors)) {
            foreach ($validationErrors as $field => $error) {
                FlashMessage::add('error', "$field: $error");
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validationErrors
            ], 422);
        }

        $ok = $this->repository->update($updatedUserEntity);
        if ($ok === false) {
            FlashMessage::add('error', 'Failed to update profile.');
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update profile.'
            ], 500);
        }

        FlashMessage::add('success', 'Profile updated successfully.');
        return new JsonResponse([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => [
                'user' => [
                    'id'         => $updatedUserEntity->getId(),
                    'fullname'   => $updatedUserEntity->getFullname(),
                    'email'      => (string)$updatedUserEntity->getEmail(),
                    'photo'      => $updatedUserEntity->getPhoto(),
                    'username'   => $updatedUserEntity->getUsername(),
                    'bio'        => $updatedUserEntity->getBio(),
                    'phone'      => $updatedUserEntity->getPhone(),
                    'status'     => $updatedUserEntity->getStatus(),
                    'coverPhoto' => $updatedUserEntity->getCoverPhoto(),
                ]
            ]
        ], 200);
    }

    private function processPasswordReset(User $user, string $token, string $newPassword): void
    {
        $jwt = new JWT();

        try {
            // Vérifie la validité du token (check() lance une exception si invalide ou expiré)
            $jwt->check($token, ['key' => env('JWT_SECRET')]);
        } catch (\Exception $e) {
            FlashMessage::add('error', $e->getMessage()); // contiendra "JWT token has expired." si expiré
            RedirectResponse::to("auth/forgot-password")->send();
            exit;
        }

        // Hash le mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->setPassword($hashedPassword);

        // Révoque les refresh tokens
        $user->setRefreshToken(null);

        // Mise à jour via le repository
        $this->repository->update($user);

        // Success + redirection
        FlashMessage::add('success', "Your password has been successfully reset.");
        RedirectResponse::to("auth/login")->send();
        exit;
    }

    private function prepareUpdatedUserEntity(User $user, array $post): User
    {
        return new User(
            id: $user->getId(),
            fullname: $post['full_name'] ?? $user->getFullname(),
            email: isset($post['email']) && $post['email'] !== ''
                ? new Email($post['email'])
                : $user->getEmail(),
            photo: $user->getPhoto(),
            password: $user->getPassword(),
            roles: $user->getRoles(),
            status: $user->getStatus(), // IMPORTANT
            verifiedEmail: $user->getVerifiedEmail(),
            coverPhoto: $user->getCoverPhoto(),
            accessToken: $user->getAccessToken(),
            refreshToken: $user->getRefreshToken(),
            bio: $post['bio'] ?? $user->getBio(),
            phone: $post['phone_number'] ?? $user->getPhone(),
            username: $user->getUsername(),
            cityName: $user->getCityName(),
            countryName: $user->getCountryName(),
            countryImageUrl: $user->getCountryImageUrl()
        );
    }

    private function validateUserEntity(User $userEntity): array
    {
        $errors = [];
        $validator = new UserValidator($this->repository);

        $fieldsToValidate = [
            'fullname' => $userEntity->getFullname(),
            'email'    => (string)$userEntity->getEmail(),
            'bio'      => $userEntity->getBio(),
            'phone'    => $userEntity->getPhone(),
            'username' => $userEntity->getUsername(),
        ];

        foreach ($fieldsToValidate as $field => $value) {
            if ($error = $validator->validateField($field, $value)) {
                $errors[$field] = $error;
            }
        }

        return $errors;
    }

    public function resetPassword(array $post): JsonResponse
    {
        try {
            // Vérifie le token CSRF
            Csrf::verifyToken($post['csrf_token'] ?? null);

            $token = trim($post['token'] ?? '');
            $newPassword = trim($post['new_password'] ?? '');

            if ($token === '' || $newPassword === '') {
                $msg = "Reset token or new password is missing.";
                FlashMessage::add('error', $msg);
                return new JsonResponse(['success' => false, 'message' => $msg], 422);
            }

            // Validation du mot de passe
            $errorPwd = UserValidator::validatePassword($newPassword);
            if ($errorPwd) {
                FlashMessage::add('error', $errorPwd);
                return new JsonResponse(['success' => false, 'message' => $errorPwd], 422);
            }

            $userRepository = new UserRepository();
            $user = $userRepository->findUserWithStatsByResetToken($token);

            if (!$user) {
                $msg = "No user found for this reset token.";
                FlashMessage::add('error', $msg);
                return new JsonResponse(['success' => false, 'message' => $msg], 404);
            }

            // Reset du mot de passe
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $user->setPassword($hashedPassword);
            $user->setRefreshToken(null);
            $userRepository->update($user);

            $msg = "Your password has been successfully reset.";
            FlashMessage::add('success', $msg);

            return new JsonResponse([
                'success' => true,
                'message' => $msg,
                'data'    => [
                    'userId' => $user->getId(),
                    'email'  => (string)$user->getEmail()
                ]
            ], 200);
        } catch (\Exception $e) {
            $msg = "Erreur : " . $e->getMessage();
            FlashMessage::add('error', $msg);
            return new JsonResponse(['success' => false, 'message' => $msg], 500);
        }
    }
}

//   /** Retourne le rôle principal d'un user ou 'user' par défaut */
//     private function roleNameOf($user): string
//     {
//         if (method_exists($user, 'getRoleName') && $user->getRoleName()) return $user->getRoleName();
//         if (method_exists($user, 'getRole') && $user->getRole()) return $user->getRole();
//         if (method_exists($user, 'getRoleNames')) {
//             $all = (array)$user->getRoleNames();
//             return $all[0] ?? 'user';
//         }
//         return 'user';
//     }

//     private function validateAndResetPassword(string $token, string $newPassword): void
//     {
//         // Validation du mot de passe
//         $errorPwd = UserValidator::validatePassword($newPassword);
//         if ($errorPwd) {
//             FlashMessage::add('error', $errorPwd);
//             RedirectResponse::to("auth/reset-password?token=$token")->send();
//             exit;
//         }

//         $userRepository = new UserRepository();

//         // Recherche l'utilisateur correspondant au token
//         $user = $userRepository->findUserWithStatsByResetToken($token);

//         if (!$user) {
//             FlashMessage::add('error', "Invalid or expired reset token.");
//             RedirectResponse::to("auth/forgot-password")->send();
//             exit;
//         }

//         // Reset du mot de passe
//         $this->processPasswordReset($user, $token, $newPassword);
//     }

//     /** Synchronise tous les rôles entre source et target, en incluant le principal */
//     private function syncAllRolesIfAny($sourceUser, $targetUser): void
//     {
//         if (method_exists($sourceUser, 'getRoleNames') && method_exists($targetUser, 'setRoleNames')) {
//             $roles = array_values(array_filter((array)$sourceUser->getRoleNames()));
//             if (!empty($roles)) $targetUser->setRoleNames($roles);
//         }
//         if (method_exists($targetUser, 'getRoleName') && method_exists($targetUser, 'getRoleNames') && method_exists($targetUser, 'setRoleNames')) {
//             $primary = $targetUser->getRoleName();
//             if ($primary) {
//                 $all = $targetUser->getRoleNames();
//                 if (!in_array($primary, $all, true)) {
//                     $all[] = $primary;
//                     $targetUser->setRoleNames($all);
//                 }
//             }
//         }
//     }

//     // if (!empty($_POST['remember'])) {
//     //     $this->repository->createRememberMeToken($user);
//     // }