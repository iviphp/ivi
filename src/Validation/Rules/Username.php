<?php

namespace App\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

final class Username implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        return (bool)preg_match('/^[A-Za-z0-9_]{3,20}$/', (string)$value);
    }

    public function message(string $field): string
    {
        return 'The :attribute must contain only letters, numbers, or underscores (3-20 chars).';
    }
}

// use App\Validation\Rules\Username;

// $data = $this->validate($request, [
//     'username' => [new Username(), 'required'],
// ]);
