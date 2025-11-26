<?php

declare(strict_types=1);

namespace Modules\User\Core\ValueObjects;

use InvalidArgumentException;

class Email
{
    private string $email;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: $email");
        }
        $this->email = $email;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
