<?php

namespace Modules\User\Core\Services;

use Exception;
use Ivi\Http\JsonResponse;
use Ivi\Http\Response;
use Modules\Auth\Core\Helpers\AuthUser;
use Modules\Utils\Image\PhotoHandler;
use Modules\User\Core\Repositories\UserRepository;
use Modules\User\Core\Validator\UserValidator;

abstract class BaseService
{
    /** @var AuthUser */
    protected AuthUser $authUser;

    protected UserRepository $repository;
    protected int $jwtValidity = 3600 * 24; // 24h

    protected ?UserValidator $validator = null;
    protected $tokenGenerator;
    /** Handler pour intercepter JsonResponse dans les tests */
    protected $jsonResponseHandler = null;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;

        $this->authUser = new AuthUser();
    }

    /**
     * Détecte si l’appel attend du JSON (API/XHR)
     */
    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return (stripos($accept, 'application/json') !== false) || ($xhr === 'XMLHttpRequest');
    }

    /**
     * Récupère l'utilisateur authentifié
     * @param bool $requireAuth Bloque si non authentifié
     */
    protected function getUserEntity(bool $requireAuth = true)
    {
        $user = $this->authUser->getUser();

        if (!$user && $requireAuth) {
            if ($this->wantsJson()) {
                Response::json(['error' => 'You must be logged in.'], 401);
            } else {
                Response::redirect('/login');
            }
        }

        return $user;
    }

    /**
     * Gestion simple de l’upload d’images
     */
    protected static function handleImages(array $files, string $directory, string $prefix = 'softadastra'): array
    {
        if (!isset($files['tmp_name']) || !is_array($files['tmp_name']) || empty(array_filter($files['tmp_name']))) {
            throw new Exception("You haven't selected any images to upload.");
        }

        if (count($files['tmp_name']) > 20) {
            throw new Exception("You can only upload up to 20 images.");
        }

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new Exception("Unable to create upload directory.");
        }

        $uploadedImages = [];
        $errors = [];

        foreach ($files['tmp_name'] as $key => $tmp_name) {
            $fileName = $files['name'][$key] ?? 'Unknown file';

            try {
                if (empty($tmp_name) || $files['error'][$key] === UPLOAD_ERR_NO_FILE) {
                    throw new Exception("No file selected.");
                }

                if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                    throw new Exception("Upload error for file: $fileName");
                }

                $file = [
                    'name' => $fileName,
                    'type' => $files['type'][$key] ?? '',
                    'tmp_name' => $tmp_name,
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key] ?? 0
                ];

                $uploadedImages[] = PhotoHandler::photo($file, $prefix, $directory);
            } catch (Exception $e) {
                $errors[] = "File '$fileName': " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            foreach ($uploadedImages as $image) {
                @unlink($directory . '/' . $image);
            }
            throw new Exception(implode("\n", $errors));
        }

        return $uploadedImages;
    }

    /** Helper interne pour centraliser l'envoi de JSON */
    protected function sendJson(array $data, int $status): void
    {
        $response = new JsonResponse($data, $status);

        if ($this->jsonResponseHandler) {
            // Interception pour les tests
            ($this->jsonResponseHandler)($response);
        } else {
            $response->send();
        }
    }

    /** Normalise et sécurise le paramètre `next` */
    protected function safeNextFromRequest(string $default = '/'): string
    {
        $next = $_GET['next'] ?? $_POST['next'] ?? ($_SESSION['post_auth_next'] ?? '');
        if (!$next) return $default;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        $nextHost   = parse_url($next, PHP_URL_HOST) ?? '';
        $nextScheme = parse_url($next, PHP_URL_SCHEME) ?? '';

        if ((strpos($next, '//') === false && str_starts_with($next, '/')) ||
            ($nextHost === $host && ($nextScheme === '' || $nextScheme === $scheme))
        ) {
            return $next;
        }

        return $default;
    }

    /** Ajoute un hash #__sa_after_login pour scroll/restoration front */
    protected function withAfterLoginHash(string $url): string
    {
        return str_contains($url, '#') ? $url : ($url . '#__sa_after_login');
    }

    public function setJsonResponseHandler(callable $handler): void
    {
        $this->jsonResponseHandler = $handler;
    }

    public function setTokenGenerator(callable $generator): void
    {
        $this->tokenGenerator = $generator;
    }

    public function setValidator(UserValidator $validator): void
    {
        $this->validator = $validator;
    }
}
