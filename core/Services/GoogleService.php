<?php

namespace Ivi\Core\Services;

use Google\Client;

/**
 * GoogleService
 *
 * A wrapper service for Google OAuth authentication using the official Google API Client.
 * 
 * This service is fully configurable via an array of options, typically loaded from
 * config/google.php, and allows controllers to generate OAuth login URLs and access
 * the Google\Client instance.
 *
 * Example usage:
 *   $config = config('google');
 *   $googleService = new GoogleService($config);
 *   $loginUrl = $googleService->loginUrl();
 */
class GoogleService
{
    /**
     * @var Client The Google API Client instance
     */
    private Client $client;

    /**
     * GoogleService constructor.
     *
     * Initializes the Google\Client with credentials and scopes from configuration.
     *
     * @param array $config Configuration array containing:
     *                      - 'client_id'     : Google OAuth Client ID
     *                      - 'client_secret' : Google OAuth Client Secret
     *                      - 'redirect_uri'  : Redirect URI after Google login
     *                      - 'scopes'        : Array of OAuth scopes
     */
    public function __construct(array $config)
    {
        $this->client = new Client();

        $this->client->setClientId($config['client_id']);
        $this->client->setClientSecret($config['client_secret']);
        $this->client->setRedirectUri($config['redirect_uri']);

        foreach ($config['scopes'] as $scope) {
            $this->client->addScope($scope);
        }
    }

    /**
     * Get the underlying Google Client instance.
     *
     * @return Client
     */
    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Generate the Google OAuth login URL.
     *
     * @return string The URL where the user should be redirected to authenticate via Google.
     */
    public function loginUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Fetch Google user profile using OAuth code.
     *
     * @param string $code The authorization code returned by Google.
     * @return object|null Returns an object with email, name, picture, verifiedEmail or null on failure.
     */
    public function fetchUser(string $code): ?object
    {
        try {
            // Échanger le code contre un token
            $token = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \RuntimeException('Google token error: ' . $token['error']);
            }

            $this->client->setAccessToken($token);

            // Obtenir le profil utilisateur
            $oauth2 = new \Google\Service\Oauth2($this->client);
            $userInfo = $oauth2->userinfo->get();

            return (object)[
                'email' => $userInfo->email ?? null,
                'name'  => $userInfo->name ?? null,
                'picture' => $userInfo->picture ?? null,
                'verifiedEmail' => $userInfo->verifiedEmail ?? false,
            ];
        } catch (\Throwable $e) {
            error_log('[GoogleService] fetchUser failed: ' . $e->getMessage());
            return null;
        }
    }
}
