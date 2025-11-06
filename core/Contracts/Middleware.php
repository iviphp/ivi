<?php

declare(strict_types=1);

namespace Ivi\Core\Contracts;

use Ivi\Http\Request;

interface Middleware
{
    /**
     * Process the request before it hits the route handler.
     * Return the (maybe mutated) Request.
     */
    public function handle(Request $request): Request;
}
