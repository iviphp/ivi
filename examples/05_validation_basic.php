<?php

declare(strict_types=1);

use Ivi\Core\Validation\{Validator, ValidationException};

// Basic validation example (create user form-like payload)
$input = [
    'name'     => 'Alice',
    'email'    => 'alice@example.com',
    'password' => 'secret123',
    'active'   => '1',
];

$rules = [
    'name'     => 'required|string|min:3|max:50',
    'email'    => 'required|email|max:120',
    'password' => 'required|string|min:6',
    'active'   => 'sometimes', // checkbox-like
];

try {
    $validated = (new Validator($input, $rules))->validate();
    echo "[OK] Validated data\n";
    var_export($validated);
    echo "\n";
} catch (ValidationException $e) {
    echo "[ERROR] Validation failed:\n";
    var_export($e->errors()->all());
    echo "\n";
}
