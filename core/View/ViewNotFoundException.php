<?php

declare(strict_types=1);

namespace Ivi\Core\View;

use Ivi\Http\Exceptions\HttpException;

final class ViewNotFoundException extends HttpException
{
    public function __construct(string $path)
    {
        parent::__construct("View not found: {$path}", 404, ['X-Ivi-Error' => 'User']);
    }
}
