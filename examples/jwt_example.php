<?php

require_once '../vendor/autoload.php'; // Assure-toi que le fichier JWT.php est inclus

use Ivi\Core\Jwt\JWT;

$jwt = new JWT();

// --------------------------
// 1️⃣ HS256 Example
// --------------------------
echo "--- HS256 Example ---\n";

// Secret long pour HS256 (mutualisé)
$secret = base64_encode(random_bytes(32));

// Payload
$payload = [
    'sub' => 123,
    'role' => 'admin',
];

// Génération du token HS256
$tokenHS = $jwt->generate($payload, [
    'key' => $secret,
    'alg' => 'HS256',
    'validity' => 3600, // 1h
]);

echo "HS256 Token: $tokenHS\n";

// Vérification du token
try {
    $jwt->check($tokenHS, ['key' => $secret]);
    echo "HS256 Token is valid.\n";

    $decodedPayload = $jwt->getPayload($tokenHS);
    echo "Payload: " . json_encode($decodedPayload) . "\n";
} catch (Exception $e) {
    echo "HS256 Error: " . $e->getMessage() . "\n";
}

// --------------------------
// 2️⃣ RS256 Example
// --------------------------
echo "\n--- RS256 Example ---\n";

// Clés générées localement, uploadées en toute sécurité sur le serveur
$privateKey = file_get_contents(dirname(__DIR__) . '/private.pem'); // Garde privé
$publicKey  = file_get_contents(dirname(__DIR__) . '/public.pem');  // Distribuable

// Génération du token RS256
$tokenRS = $jwt->generate($payload, [
    'key' => $privateKey,
    'alg' => 'RS256',
    'validity' => 3600,
]);

echo "RS256 Token: $tokenRS\n";

// Vérification du token RS256
try {
    $jwt->check($tokenRS, ['key' => $publicKey]);
    echo "RS256 Token is valid.\n";

    $decodedPayloadRS = $jwt->getPayload($tokenRS);
    echo "Payload: " . json_encode($decodedPayloadRS) . "\n";
} catch (Exception $e) {
    echo "RS256 Error: " . $e->getMessage() . "\n";
}
// openssl genrsa -out private.pem 4096
// openssl rsa -in private.pem -pubout -out public.pem