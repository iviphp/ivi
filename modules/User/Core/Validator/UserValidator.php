<?php

declare(strict_types=1);

namespace Modules\User\Core\Validator;

use Modules\User\Core\Models\User;
use Modules\User\Core\Helpers\UserHelper;
use Modules\User\Core\Repositories\UserRepository;

class UserValidator
{
    private UserRepository $userRepository;
    private const ALLOWED_STATUSES = ['active', 'inactive', 'banned'];

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Valide toute l'entité User.
     * Retourne un tableau associatif d'erreurs (champ => message).
     */
    public function validate(User $user): array
    {
        $errors = [];

        // --- Fullname ---
        $fullname = trim($user->getFullname());
        if ($fullname === '') {
            $errors['fullname'] = 'Full name is required.';
        } elseif (mb_strlen($fullname) > 255) {
            $errors['fullname'] = 'Full name cannot exceed 255 characters.';
        }

        // --- Email ---
        $email = (string)$user->getEmail();
        if ($err = self::validateEmail($email)) {
            $errors['email'] = $err;
        } elseif ($this->userRepository->findByEmail($email)) {
            $errors['email'] = 'This email is already taken.';
        }

        // --- Password ---
        $password = $user->getPassword();
        if ($err = self::validatePassword($password)) {
            $errors['password'] = $err;
        }

        // --- Username ---
        $username = $user->getUsername();
        if ($username === null || trim($username) === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[a-z0-9]+$/i', $username)) {
            $errors['username'] = 'Username can only contain letters and numbers.';
        } elseif ($this->userRepository->findByUsername($username)) {
            $errors['username'] = 'This username is already taken.';
        }

        // --- Roles ---
        $roles = $user->getRoles();
        if (empty($roles)) {
            $errors['roles'] = 'At least one role is required.';
        }

        // --- Status ---
        if (!in_array($user->getStatus(), self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Invalid status.';
        }

        // --- Cover photo ---
        $cover = $user->getCoverPhoto();
        if ($cover && mb_strlen($cover) > 255) {
            $errors['coverPhoto'] = 'Cover photo path is too long.';
        }

        // --- Bio ---
        $bio = $user->getBio();
        if ($bio && mb_strlen($bio) > 500) {
            $errors['bio'] = 'Bio cannot exceed 500 characters.';
        }

        return $errors;
    }

    /**
     * Valide un champ isolé (fullname, email, password, phone, username, etc.)
     * Retourne le message d'erreur ou null si OK.
     */
    public function validateField(string $field, mixed $value): ?string
    {
        return match ($field) {
            'fullname' => $value === '' ? 'Full name is required.' : (mb_strlen($value) > 255 ? 'Full name cannot exceed 255 characters.' : null),
            'email' => self::validateEmail((string)$value),
            'password' => self::validatePassword((string)$value),
            'phone' => self::validatePhoneNumber((string)$value),
            'username' => $this->validateUsername((string)$value),
            default => null,
        };
    }

    /** Validation statique d'un email. */
    public static function validateEmail(?string $email): ?string
    {
        if (!$email) {
            return 'Email is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format.';
        }
        return null;
    }

    /** Validation statique d'un mot de passe. */
    public static function validatePassword(?string $password): ?string
    {
        if (!$password) {
            return 'Password is required.';
        }
        if (!UserHelper::validatePasswordPolicy($password)) {
            return 'Password must be at least 8 characters long, include uppercase, lowercase and number.';
        }
        return null;
    }

    /** Validation statique d'un numéro de téléphone au format E.164. */
    public static function validatePhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return 'Phone number is required.';
        }
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            return 'Phone number must be in E.164 format.';
        }
        return null;
    }

    /** Validation d'un username. */
    private function validateUsername(string $username): ?string
    {
        $username = trim($username);
        if ($username === '') return 'Username is required.';
        if (!preg_match('/^[a-z0-9]+$/i', $username)) return 'Username can only contain letters and numbers.';
        if ($this->userRepository->findByUsername($username)) return 'This username is already taken.';
        return null;
    }
}
