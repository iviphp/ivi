<?php

namespace Ivi\Core\Jwt;

use DateTime;
use Exception;

class JWT
{
    private const SUPPORTED_ALG = ['HS256', 'RS256'];

    /**
     * Generates a JWT token.
     *
     * @param array $payload JWT payload.
     * @param array $options Optional settings:
     *   - 'key' => string Secret for HS256 or private key PEM for RS256.
     *   - 'alg' => string 'HS256' or 'RS256'.
     *   - 'validity' => int Token validity in seconds (default 24h).
     * @return string JWT token.
     * @throws Exception If signing fails or unsupported algorithm.
     */
    public function generate(array $payload, array $options = []): string
    {
        $alg = $options['alg'] ?? 'HS256';
        $key = $options['key'] ?? null;
        $validity = $options['validity'] ?? 86400;

        if (!in_array($alg, self::SUPPORTED_ALG)) {
            throw new Exception("Unsupported JWT algorithm: $alg");
        }

        if ($alg === 'HS256' && empty($key)) {
            throw new Exception("HS256 requires a secret key.");
        }

        if ($alg === 'RS256' && empty($key)) {
            throw new Exception("RS256 requires a private key.");
        }

        $header = [
            'typ' => 'JWT',
            'alg' => $alg,
        ];

        $now = new DateTime();
        $payload['iat'] = $now->getTimestamp();
        if ($validity > 0) {
            $payload['exp'] = $payload['iat'] + $validity;
        }

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("$headerB64.$payloadB64", $key, $alg);

        return "$headerB64.$payloadB64.$signature";
    }

    /**
     * Validates a JWT token.
     *
     * @param string $token JWT token.
     * @param array $options Optional:
     *   - 'key' => secret for HS256 or public key PEM for RS256.
     * @return bool True if valid.
     * @throws Exception If invalid or expired.
     */
    public function check(string $token, array $options = []): bool
    {
        if (!$this->isValidFormat($token)) {
            throw new Exception("Invalid JWT token format.");
        }

        [$headerB64, $payloadB64, $signatureB64] = explode('.', $token);

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        $alg = $header['alg'] ?? null;
        if (!in_array($alg, self::SUPPORTED_ALG)) {
            throw new Exception("Unsupported or missing JWT algorithm.");
        }

        $key = $options['key'] ?? null;
        if (($alg === 'HS256' && empty($key)) || ($alg === 'RS256' && empty($key))) {
            throw new Exception("$alg requires a key for verification.");
        }

        if (!$this->verify("$headerB64.$payloadB64", $signatureB64, $key, $alg)) {
            throw new Exception("Invalid JWT token signature.");
        }

        $now = new DateTime();
        if (isset($payload['exp']) && $payload['exp'] < $now->getTimestamp()) {
            throw new Exception("JWT token has expired.");
        }

        if (isset($payload['iat']) && $payload['iat'] > $now->getTimestamp()) {
            throw new Exception("JWT token 'issued at' is in the future.");
        }

        return true;
    }

    /**
     * Returns the JWT payload as an array.
     *
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function getPayload(string $token): array
    {
        return $this->decodePart($token, 1, 'payload');
    }

    /**
     * Returns the JWT header as an array.
     *
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function getHeader(string $token): array
    {
        return $this->decodePart($token, 0, 'header');
    }

    /**
     * Checks if the token format is valid.
     *
     * @param string $token
     * @return bool
     */
    public function isValidFormat(string $token): bool
    {
        return preg_match('/^[a-zA-Z0-9\-_]+\.[a-zA-Z0-9\-_]+\.[a-zA-Z0-9\-_]+$/', $token) === 1;
    }

    // ----------------------
    // Private helpers
    // ----------------------

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function sign(string $data, ?string $key, string $alg): string
    {
        if ($alg === 'HS256') {
            $hash = hash_hmac('sha256', $data, $key, true);
            return $this->base64UrlEncode($hash);
        }

        if ($alg === 'RS256') {
            $signature = '';
            if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
                throw new Exception("Failed to sign JWT token with RS256.");
            }
            return $this->base64UrlEncode($signature);
        }

        throw new Exception("Unsupported JWT algorithm: $alg");
    }

    private function verify(string $data, string $signatureB64, ?string $key, string $alg): bool
    {
        $signature = $this->base64UrlDecode($signatureB64);

        if ($alg === 'HS256') {
            $expected = hash_hmac('sha256', $data, $key, true);
            return hash_equals($expected, $signature);
        }

        if ($alg === 'RS256') {
            return openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
        }

        return false;
    }

    private function decodePart(string $token, int $index, string $partName): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception("JWT token format is invalid.");
        }

        $data = json_decode($this->base64UrlDecode($parts[$index]), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JWT token $partName is corrupted.");
        }

        return $data;
    }
}
