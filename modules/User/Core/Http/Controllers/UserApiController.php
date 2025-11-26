<?php

namespace Modules\User\Core\Http\Controllers;

use App\Controllers\Controller;
use Ivi\Http\JsonResponse;
use Modules\Auth\Core\Helpers\AuthUser;
use Modules\Auth\Core\Helpers\AuthRedirect;

class UserApiController extends Controller
{
    /**
     * Renvoie les infos du user connecté
     * GET /api/user/me
     */
    public function me(): JsonResponse
    {
        // Redirection si guest
        AuthRedirect::redirectIfGuest('/auth/login');

        $auth = new AuthUser();
        $user = $auth->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        return new JsonResponse([
            'success' => true,
            'user'    => [
                'id'        => $user->getId(),
                'email'     => $user->getEmail(),
                'username'  => $user->getUsername(),
                'roles'     => $user->getRoleNames(),
                'photo'     => $user->getPhoto(),
                'cover'     => $user->getCoverPhoto(),
                'city'      => $user->getCityName(),
                'country'   => $user->getCountryImageUrl(),
                'products'  => $user->getProductCount(),
            ],
        ]);
    }
}
