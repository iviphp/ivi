<?php

declare(strict_types=1);

namespace Modules\Auth\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\JsonResponse;
use Ivi\Http\HtmlResponse;
use Ivi\Core\Services\GoogleService;
use Ivi\Core\Utils\FlashMessage;
use Ivi\Http\RedirectResponse;
use Modules\User\Core\Services\UserService;
use Ivi\Http\Request;
use Modules\Auth\Core\Helpers\AuthRedirect;
use Modules\Auth\Core\Helpers\AuthUser;
use Modules\User\Core\Helpers\UserHelper;

class AuthController extends Controller
{
    private UserService $users;
    private GoogleService $google;

    public function __construct()
    {
        $config = config_value('google');
        if (!$config || !is_array($config)) {
            throw new \RuntimeException(
                "Google configuration not found. Ensure config/google.php exists and returns an array."
            );
        }

        $this->google = new GoogleService($config);
        $this->users = make(UserService::class);
    }

    // ---------------------------------------------------
    // GET PAGES
    // ---------------------------------------------------
    public function home(): HtmlResponse
    {
        AuthRedirect::redirectIfAuthenticated();

        $styles = module_asset('Auth/Core', 'assets/css/home.css');

        return $this->view('auth::home', [
            'title'     => 'Auth Home',
            'styles'    => $styles,
            'googleUrl' => $this->google->loginUrl(),
        ]);
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $code = $request->get('code');
            if (!$code) {
                FlashMessage::add('error', "Google login failed: missing authorization code.");
                RedirectResponse::to('/auth/login')->send();
                return;
            }

            $googleUser = $this->google->fetchUser($code);
            if (!$googleUser || empty($googleUser->email)) {
                FlashMessage::add('error', "Google login failed: unable to fetch Google account.");
                RedirectResponse::to('/auth/login')->send();
                return;
            }

            $this->users->loginWithGoogleOAuth($googleUser);

            return;
        } catch (\Throwable $e) {
            error_log("[Google OAuth] Exception: " . $e->getMessage());
            FlashMessage::add('error', "Unexpected error during Google login.");
            RedirectResponse::to('/auth/login')->send();
        }
    }

    public function logout(): JsonResponse
    {
        AuthUser::logout();

        return new JsonResponse([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    public function showLoginForm(): HtmlResponse
    {
        AuthRedirect::redirectIfAuthenticated();

        $styles  = module_asset('Auth/Core', 'assets/css/login.css');
        $scripts = module_asset('Auth/Core', 'assets/js/login.js');
        error_log("CSRF token session: " . ($_SESSION['csrf_token'] ?? 'NULL'));


        return $this->view('auth::login', [
            'title'     => 'Login',
            'styles'    => $styles,
            'scripts'   => $scripts,
        ]);
    }

    public function showRegistrationForm(): HtmlResponse
    {
        AuthRedirect::redirectIfAuthenticated();

        $styles  = module_asset('Auth/Core', 'assets/css/register.css');
        $scripts = module_asset('Auth/Core', 'assets/js/register.js');

        return $this->view('auth::register', [
            'title'     => 'Register',
            'styles'    => $styles,
            'scripts'   => $scripts,
            'googleUrl' => $this->google->loginUrl(),
        ]);
    }

    public function handleRegistration(Request $request): JsonResponse
    {
        $data = $request->all();

        $fullname = (string)($data['fullname'] ?? '');
        $email    = (string)($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');

        $result = $this->users->register($fullname, $email, $password);

        $status = empty($result['errors']) ? 201 : 422;

        if (!isset($result['errors']) || $result['errors'] === null) {
            $result['errors'] = [];
        }

        return new JsonResponse($result, $status);
    }

    public function handleLogin(Request $request): JsonResponse
    {
        $data = $request->all();
        $email = (string)($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $next = (string)($data['next'] ?? '/');

        // Lire token CSRF : body (csrf_token) ou header X-CSRF-TOKEN
        $csrfFromBody = $data['csrf_token'] ?? null;
        $csrfFromHeader = $request->header('X-CSRF-TOKEN'); // <-- use public accessor
        $csrfToken = $csrfFromBody ?? $csrfFromHeader ?? null;

        try {
            // Vérification CSRF : renvoie une exception en cas d'échec
            \Ivi\Core\Security\Csrf::verifyToken($csrfToken, true);

            if (!$email || !$password) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['email' => 'Email and password are required.'],
                    'message' => 'Invalid login data.',
                    'redirect' => $next,
                ], 422);
            }

            $user = $this->users->findByEmail($email);

            if (!$user || !UserHelper::verifyPassword($password, $user->getPassword())) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['email' => 'Invalid email or password.'],
                    'message' => 'Login failed.',
                    'redirect' => $next,
                ], 401);
            }

            // --- Auth complet : token + session + cookie ---
            $token = AuthUser::login($user);

            // Générer un nouveau CSRF token pour la session post-login (sécurité)
            $newCsrf = \Ivi\Core\Security\Csrf::generateToken(true);

            return new JsonResponse([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoleNames(),
                ],
                'message' => 'Login successful.',
                'errors' => [],
                'redirect' => $next,
                'csrf_token' => $newCsrf,
            ], 200);
        } catch (\RuntimeException $e) {
            error_log("[Auth] CSRF verification failed: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'errors' => ['csrf' => 'Invalid CSRF token.'],
                'message' => 'CSRF verification failed.',
                'redirect' => $next,
            ], 419);
        } catch (\Throwable $e) {
            error_log("[Auth] Login exception: " . $e->__toString());
            return new JsonResponse([
                'success' => false,
                'errors' => ['exception' => $e->getMessage()],
                'message' => 'Uh-oh! Something went wrong. [Auth]',
                'redirect' => $next,
            ], 500);
        }
    }

    public function showSyncPage(): HtmlResponse
    {
        $styles = module_asset('Auth/Core', 'assets/css/home.css');

        return $this->view('auth::sync', [
            'title'     => 'Sync Page',
            'styles'    => $styles
        ]);
    }
}
