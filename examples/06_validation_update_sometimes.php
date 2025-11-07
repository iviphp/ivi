<?php

declare(strict_types=1);

use Ivi\Core\Validation\{Validator, ValidationException};

// Update scenario: empty password is ignored, name/email are 'sometimes|required'
$post = [
    'name'     => 'Bo',        // too short to pass min:3
    'email'    => 'bob@site',  // invalid email
    'password' => '',          // ignored if removed before validate
];

// Remove empty password to keep current one
if (array_key_exists('password', $post) && trim((string)$post['password']) === '') {
    unset($post['password']);
}

$rules = [
    'name'     => 'sometimes|required|string|min:3|max:50',
    'email'    => 'sometimes|required|email|max:120',
    'password' => 'sometimes|string|min:6',
    'active'   => 'sometimes',
];

try {
    $validated = (new Validator($post, $rules))->validate();
    echo "[OK] Update payload is valid\n";
    var_export($validated);
    echo "\n";
} catch (ValidationException $e) {
    echo "[ERROR] Update validation failed:\n";
    foreach ($e->errors()->all() as $field => $messages) {
        foreach ($messages as $m) {
            echo "- {$field}: {$m}\n";
        }
    }
}
